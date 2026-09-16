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

        $declaration->update([
            'statut' => 'validee',
            'moderateur_id' => $moderateur->id,
        ]);

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

        // 7. Notifier le citoyen
        $this->notifierCitoyen(
            $declaration,
            "Votre déclaration #{$declaration->id} a été validée."
        );

        if (! $facebookResponse['success'] || ($instagramResponse && ! $instagramResponse['success'])) {
            return back()
                ->with('warning', 'Déclaration validée, mais sa publication sur au moins un réseau social a échoué. Contactez l’administrateur si nécessaire.');
        }

        return back()->with('success', $imageUrl
            ? 'Déclaration validée et publiée sur Facebook/Instagram.'
            : 'Déclaration validée et publiée sur Facebook. Instagram ignoré car aucune image n’est jointe.');
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
