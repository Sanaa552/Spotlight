<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use App\Services\MetaPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

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
        $lock = Cache::lock('validation-declaration-'.$declaration->id, 300);

        if (! $lock->get()) {
            return back()->with('warning', 'Une publication de cette déclaration est déjà en cours. Réessayez dans quelques instants.');
        }

        try {
            $declaration->refresh()->load('localisation');

            if ($declaration->statut !== 'en_attente') {
                return back()->with('warning', 'Cette déclaration a déjà été traitée. Actualisez la liste.');
            }

            if (! $declaration->photo_path || ! Storage::disk('public')->exists($declaration->photo_path)) {
                Log::warning('Publication impossible sans photo publique Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $moderateur->id,
                ]);

                return back()->with('warning', 'Cette déclaration ne possède pas de photo publique. Elle reste en attente ; contactez l’administrateur.');
            }

            $imageUrl = $declaration->photoUrl();
            $message = $this->messagePublication($declaration);

            if (! $declaration->facebook_post_id) {
                $facebookResponse = $this->metaPublishingService->publishToFacebook($message, $imageUrl);
                Log::log($facebookResponse['success'] ? 'info' : 'warning', 'Publication Facebook Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $moderateur->id,
                    'result' => $facebookResponse,
                ]);

                $facebookId = $facebookResponse['response']['id'] ?? null;
                if (! $facebookResponse['success'] || ! $facebookId) {
                    return back()->with('warning', 'Publication Facebook non confirmée. La déclaration reste en attente ; contactez l’administrateur si nécessaire.');
                }

                $declaration->update(['facebook_post_id' => $facebookId]);
            }

            if (! $declaration->instagram_post_id) {
                $instagramResponse = $this->metaPublishingService->publishToInstagram($imageUrl, $message);
                Log::log($instagramResponse['success'] ? 'info' : 'warning', 'Publication Instagram Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $moderateur->id,
                    'result' => $instagramResponse,
                ]);

                $instagramId = $instagramResponse['response']['id'] ?? null;
                if (! $instagramResponse['success'] || ! $instagramId) {
                    return back()->with('warning', 'Facebook a été publié, mais Instagram n’a pas confirmé la publication. La déclaration reste en attente ; réessayez après correction. Facebook ne sera pas republié.');
                }

                $declaration->update(['instagram_post_id' => $instagramId]);
            }

            $declaration->publier();
            $declaration->update(['moderateur_id' => $moderateur->id]);

            try {
                $this->notifierCitoyen($declaration, "Votre déclaration #{$declaration->id} a été validée.");
            } catch (Throwable $exception) {
                Log::error('Notification citoyen apres validation impossible Spotlight', [
                    'declaration_id' => $declaration->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }

            Log::info('Declaration validee apres publications Meta Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $moderateur->id,
                'facebook_post_id' => $declaration->facebook_post_id,
                'instagram_post_id' => $declaration->instagram_post_id,
            ]);

            return back()->with('success', 'Déclaration validée et publiée sur Facebook et Instagram.');
        } catch (Throwable $exception) {
            Log::error('Echec technique validation declaration Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $moderateur->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'La validation n’a pas pu être terminée. La déclaration reste en attente ; contactez l’administrateur si le problème persiste.');
        } finally {
            $lock->release();
        }
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
