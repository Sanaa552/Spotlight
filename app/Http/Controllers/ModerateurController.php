<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $moderateur = $request->user();

        $declaration->update([
            'statut' => 'validee',
            'moderateur_id' => $moderateur->id,
        ]);

        $this->notifierCitoyen(
            $declaration,
            "Votre déclaration #{$declaration->id} a été validée."
        );

        return back()->with('success', 'Déclaration validée.');
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