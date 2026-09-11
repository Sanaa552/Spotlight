<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\MetaPublishingService;

class ModerateurController extends Controller
{

    public function __construct(
    private MetaPublishingService $metaPublishingService) {

    }

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

        // 1. Valider
$declaration->update([
    'statut' => 'validee',
    'moderateur_id' => $moderateur->id,
]);

// 2. Charger les relations
$declaration->load('piecesJointes', 'localisation');

// 3. Construire le message
$message =
    "🚨 SPOTLIGHT — Déclaration #{$declaration->id}\n\n" .
    ucfirst($declaration->type) . " : {$declaration->categorie}\n\n" .
    $declaration->description . "\n\n" .
    "📍 " . (
        $declaration->localisation->adresse
        ?? $declaration->lieu
        ?? 'Lieu non précisé'
    );

// 4. Récupérer la première image
$pieceJointe = $declaration->piecesJointes
    ->first(fn ($piece) => $piece->estImage());

$imageUrl = $pieceJointe?->url();

// 5. Publication Facebook
$facebookResponse = $this->metaPublishingService->publishToFacebook(
    $message,
    $imageUrl
);

\Log::info('Publication Facebook Spotlight', [
    'declaration_id' => $declaration->id,
    'image_url' => $imageUrl,
    'response' => $facebookResponse,
]);

// 6. Publication Instagram
$instagramResponse = null;

if ($imageUrl) {
    $instagramResponse = $this->metaPublishingService->publishToInstagram(
        $imageUrl,
        $message
    );

    \Log::info('Publication Instagram Spotlight', [
        'declaration_id' => $declaration->id,
        'image_url' => $imageUrl,
        'response' => $instagramResponse,
    ]);
}

        // 7. Notifier le citoyen
        $this->notifierCitoyen(
            $declaration,
            "Votre déclaration #{$declaration->id} a été validée."
        );

        return back()->with('success', 'Déclaration validée et publiée sur les réseaux sociaux.');
    }


    /** Rejeter une déclaration */
    public function rejeter(Request $request, Declaration $declaration): RedirectResponse
    {
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

        return back()->with('success', 'Déclaration rejetée.');
    }

    /** Bloquer utilisateur */
    public function bloquerUtilisateur(User $user): RedirectResponse
    {
        $user->update(['is_blocked' => true]);

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