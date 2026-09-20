<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Jobs\PublishDeclaration;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\ModerationConfirmed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ModerateurController extends Controller
{
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
        $startedAt = microtime(true);
        $moderateur = $request->user();
        Log::info('Validation declaration demandee Spotlight', [
            'declaration_id' => $declaration->id,
            'moderateur_id' => $moderateur->id,
            'publication_status' => $declaration->publication_status,
        ]);
        $lock = Cache::lock('validation-declaration-'.$declaration->id, 300);

        if (! $lock->get()) {
            return back()->with('warning', 'Une publication de cette déclaration est déjà en cours. Réessayez dans quelques instants.');
        }

        try {
            $declaration->refresh()->load('localisation');

            if ($declaration->statut !== 'en_attente') {
                return back()->with('warning', 'Cette déclaration a déjà été traitée. Actualisez la liste.');
            }

            $requiredDocuments = $declaration->type === 'perte'
                ? ['declaration_perte']
                : ($declaration->categorie === 'objet'
                    ? ['preuve_decouverte', 'preuve_signalement']
                    : ['preuve_signalement']);

            $documents = $declaration->piecesJointes()->whereIn('type_document', $requiredDocuments)->get()->keyBy('type_document');
            foreach ($requiredDocuments as $documentType) {
                $document = $documents->get($documentType);
                if (! $document) {
                    Log::warning('Validation refusee sans preuve privee Spotlight', [
                        'declaration_id' => $declaration->id,
                        'moderateur_id' => $moderateur->id,
                        'preuve_manquante' => $documentType,
                    ]);

                    $message = $documentType === 'preuve_signalement'
                        ? 'Le justificatif des autorités manque. Demandez au citoyen de l’ajouter depuis son dossier ; aucune publication n’a été lancée.'
                        : 'Une preuve obligatoire manque. Le dossier reste en attente ; demandez un dossier complet au citoyen.';

                    return back()->with('warning', $message);
                }

                if (! Storage::disk($document->disque)->exists($document->chemin)) {
                    Log::error('Preuve privee introuvable Spotlight', [
                        'declaration_id' => $declaration->id,
                        'moderateur_id' => $moderateur->id,
                        'piece_jointe_id' => $document->id,
                    ]);

                    return back()->with('warning', 'Une preuve enregistrée est introuvable. Aucune publication n’a été lancée ; contactez l’administrateur.');
                }
            }

            if ($declaration->type === 'decouverte' && $declaration->categorie === 'personne') {
                $declaration->confirmerSignalement();
                $declaration->update(['moderateur_id' => $moderateur->id]);
                try {
                    $this->notifierCitoyen($declaration, "Votre signalement #{$declaration->id} a été vérifié. Il reste privé.");
                    ModerationConfirmed::sendFor($declaration, $moderateur, true);
                } catch (Throwable $exception) {
                    Log::error('Notification signalement personne impossible Spotlight', [
                        'declaration_id' => $declaration->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                }

                Log::info('Signalement personne confirme sans publication Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $moderateur->id,
                ]);

                return back()->with('success', 'Signalement vérifié. Aucune publication publique ou Meta n’a été effectuée.');
            }

            if (! $declaration->photo_path) {
                Log::warning('Publication impossible sans photo publique Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $moderateur->id,
                ]);

                return back()->with('warning', 'Cette déclaration ne possède pas de photo publique. Elle reste en attente ; contactez l’administrateur.');
            }

            $photoDisk = $declaration->photoEnAttente() ? 'local' : 'public';
            if (! Storage::disk($photoDisk)->exists($declaration->photo_path)) {
                return back()->with('warning', 'La photo publique est introuvable. Aucune publication n’a été lancée ; contactez l’administrateur.');
            }

            Log::info('Validation prete pour mise en file Spotlight', [
                'declaration_id' => $declaration->id,
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            $queued = DB::transaction(function () use ($declaration, $moderateur) {
                $updated = Declaration::query()
                    ->whereKey($declaration->id)
                    ->where('statut', 'en_attente')
                    ->where(fn ($query) => $query->whereNull('publication_status')->orWhere('publication_status', 'failed'))
                    ->update([
                        'publication_status' => 'queued',
                        'publication_error' => null,
                        'moderateur_id' => $moderateur->id,
                    ]);

                if ($updated) {
                    PublishDeclaration::dispatch($declaration->id, $moderateur->id)->onConnection('database');
                }

                return $updated;
            });

            if (! $queued) {
                return back()->with('warning', 'La publication de ce dossier est déjà en cours. Actualisez la liste.');
            }

            Log::info('Publication declaration mise en file Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $moderateur->id,
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

<<<<<<< HEAD
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
=======
            return back()->with('success', 'Publication lancée en arrière-plan. Le résultat apparaîtra ici et sera notifié au citoyen.');
        } catch (Throwable $exception) {
            Log::error('Echec technique validation declaration Spotlight', [
>>>>>>> 4a99859659216a014ad8e416f606ebd6a5196d5a
                'declaration_id' => $declaration->id,
                'moderateur_id' => $moderateur->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return back()->with('warning', 'La validation n’a pas pu être terminée. La déclaration reste en attente ; contactez l’administrateur si le problème persiste.');
        } finally {
            $lock->release();
        }
<<<<<<< HEAD

        
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
=======
>>>>>>> 4a99859659216a014ad8e416f606ebd6a5196d5a
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

        $rejected = Declaration::query()
            ->whereKey($declaration->id)
            ->where('statut', 'en_attente')
            ->where(fn ($query) => $query->whereNull('publication_status')->orWhere('publication_status', 'failed'))
            ->update([
                'statut' => 'rejetee',
                'motif_rejet' => $validated['motif_rejet'],
                'moderateur_id' => $request->user()->id,
            ]);

        if (! $rejected) {
            return back()->with('warning', 'Publication en cours : impossible de rejeter ce dossier maintenant.');
        }

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

}
