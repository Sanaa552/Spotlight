<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use App\Services\MetaPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ModerateurController extends Controller
{
    public function __construct(
        private MetaPublishingService $metaPublishingService) {}

    /** Gérer déclaration : file d'attente des déclarations à traiter */
    public function index(): View
    {
        $declarations = Declaration::enAttente()
            ->with('citoyen', 'localisation')
            ->latest()
            ->paginate(15);

        return view('moderation.index', compact('declarations'));
    }

    /** Valider une déclaration */
    public function valider(Request $request, Declaration $declaration): RedirectResponse
    {
        $moderateur = $request->user();

        if ($declaration->statut !== 'en_attente') {
            Log::notice('Validation declaration ignoree Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $moderateur->id,
                'statut' => $declaration->statut,
            ]);

            return back()->with('warning', 'Cette déclaration a déjà été traitée. Actualisez la liste.');
        }

        $declaration->load('piecesJointes', 'localisation');

        $message = $this->messagePublication($declaration);
        $pieceJointe = $declaration->piecesJointes->first(
            fn ($piece) => ! $piece->estDeclarationPerte() && $piece->estImage()
        );
        $imageUrl = $pieceJointe?->url();

        Log::info('Validation declaration Spotlight', [
            'declaration_id' => $declaration->id,
            'moderateur_id' => $moderateur->id,
            'type' => $declaration->type,
            'categorie' => $declaration->categorie,
            'has_image' => filled($imageUrl),
            'image_url' => $imageUrl,
        ]);

        $facebookResponse = $this->metaPublishingService->publishToFacebook($message, $imageUrl);

        $facebookLogLevel = $facebookResponse['success'] ? 'info' : 'warning';
        Log::$facebookLogLevel('Publication Facebook Spotlight', [
            'declaration_id' => $declaration->id,
            'image_url' => $imageUrl,
            'result' => $facebookResponse,
        ]);

        $instagramResponse = null;

        if ($imageUrl) {
            $instagramResponse = $this->metaPublishingService->publishToInstagram($imageUrl, $message);

            $instagramLogLevel = $instagramResponse['success'] ? 'info' : 'warning';
            Log::$instagramLogLevel('Publication Instagram Spotlight', [
                'declaration_id' => $declaration->id,
                'image_url' => $imageUrl,
                'result' => $instagramResponse,
            ]);
        } else {
            Log::notice('Publication Instagram ignoree Spotlight', [
                'declaration_id' => $declaration->id,
                'reason' => 'Aucune image jointe. Instagram exige une image.',
            ]);
        }

        
         // 6. Vérifier que les publications obligatoires ont réussi 

         $facebookOk = $facebookResponse['success'] ?? false; 
         // Si une image existe, Instagram est obligatoire. 
         // S'il n'y a pas d'image, Instagram est impossible et est donc ignoré. 
         $instagramOk = $imageUrl ? ($instagramResponse['success'] ?? false) : true; 
          
         if (! $facebookOk || ! $instagramOk) { 
            Log::warning('Validation annulee : publication Meta incomplete Spotlight', [ 
                'declaration_id' => $declaration->id, 
                'moderateur_id' => $moderateur->id, 
                'facebook_success' => $facebookOk, 
                'instagram_success' => $instagramOk, 
                'facebook_result' => $facebookResponse, 
                'instagram_result' => $instagramResponse, ]); 

                return back()->with(
                    'error', 
                    'La déclaration n’a pas été validée car la publication sur Facebook ou Instagram a échoué. Vérifiez la connexion aux réseaux sociaux puis réessayez.' 
                    ); 
                }

                 // 7. Les publications ont réussi :
                 // maintenant seulement, on valide la déclaration. 
                 $declaration->update([
                    'statut' => 'validee', 
                    'moderateur_id' => $moderateur->id, 
                ]); 
                 
            // 8. Notifier le citoyen uniquement après validation complète 
            $this->notifierCitoyen( 
                $declaration, 
                "Votre déclaration #{$declaration->id} a été validée et publiée sur les réseaux sociaux."
            ); 
            Log::info('Declaration validee et publiee Spotlight', [ 
                'declaration_id' => $declaration->id, 
                'moderateur_id' => $moderateur->id, 
                'facebook_success' => $facebookOk, 
                'instagram_success' => $instagramOk, 
            ]); 
                
                return back()->with( 
                    'success', 
                    'Déclaration validée et publiée avec succès sur Facebook et Instagram.' 
                );
    }

    /** Rejeter une déclaration */
    public function rejeter(Request $request, Declaration $declaration): RedirectResponse
    {
        if ($declaration->statut !== 'en_attente') {
            Log::notice('Rejet declaration ignore Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $request->user()->id,
                'statut' => $declaration->statut,
            ]);

            return back()->with('warning', 'Cette déclaration a déjà été traitée. Actualisez la liste.');
        }

        $validated = $request->validate([
            'motif_rejet' => ['required', 'string', 'max:1000'],
        ]);

        $declaration->update([
            'statut' => 'rejetee',
            'motif_rejet' => $validated['motif_rejet'],
            'moderateur_id' => $request->user()->id,
        ]);

        $this->notifierCitoyen(
            $declaration,
            "Votre déclaration #{$declaration->id} a été rejetée : {$validated['motif_rejet']}"
        );

        Log::info('Declaration rejetee Spotlight', [
            'declaration_id' => $declaration->id,
            'moderateur_id' => $request->user()->id,
            'citoyen_id' => $declaration->user_id,
        ]);

        return back()->with('success', 'Déclaration rejetée.');
    }

    /** Bloquer utilisateur */
    public function bloquerUtilisateur(Request $request, User $user): RedirectResponse
    {
        if (! $user->isCitoyen()) {
            Log::warning('Blocage utilisateur refuse Spotlight', [
                'target_user_id' => $user->id,
                'actor_id' => $request->user()->id,
                'target_role' => $user->role?->value,
            ]);

            return back()->with('warning', 'Un modérateur ne peut bloquer qu’un compte citoyen.');
        }

        $user->update(['is_blocked' => true]);

        Log::info('Utilisateur bloque par moderation Spotlight', [
            'target_user_id' => $user->id,
            'moderateur_id' => $request->user()->id,
        ]);

        return back()->with('success', "Utilisateur {$user->name} bloqué.");
    }

    /** Notifier citoyen (relayé potentiellement vers l'API Twilio pour SMS) */
    private function notifierCitoyen(Declaration $declaration, string $message): AppNotification
    {
        return AppNotification::create([
            'user_id' => $declaration->user_id,
            'declaration_id' => $declaration->id,
            'message' => $message,
            'date_envoi' => now(),
            'canal' => 'app', // passer à 'sms' si envoi via TwilioNotificationService
        ]);
    }

    private function messagePublication(Declaration $declaration): string
    {
        $precision = $declaration->type === 'perte'
            ? $declaration->type_perte
            : $declaration->type_decouverte;

        return implode("\n\n", array_filter([
            "SPOTLIGHT - Declaration #{$declaration->id}",
            ucfirst($declaration->type).' : '.$declaration->categorie,
            $precision,
            $declaration->description,
            'Lieu : '.($declaration->localisation->adresse ?? $declaration->lieu ?? 'Non precise'),
        ]));
    }
}
