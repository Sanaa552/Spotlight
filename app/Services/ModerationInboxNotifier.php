<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\ModerationActionRequired;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerationInboxNotifier
{
    public function declarationSoumise(Declaration $declaration): void
    {
        $libelle = $declaration->libelleNotification();
        $this->send(
            $declaration,
            "Spotlight : {$libelle} à examiner",
            "Nouveau dossier à examiner : {$libelle}. Déclarant : {$declaration->citoyen->name}."
        );
    }

    public function preuveAjoutee(Declaration $declaration): void
    {
        $libelle = $declaration->libelleNotification();
        $this->send(
            $declaration,
            "Spotlight : justificatif ajouté à {$libelle}",
            "Un justificatif des autorités a été ajouté à {$libelle}. Le dossier peut être examiné à nouveau."
        );
    }

    public function publicationEchouee(Declaration $declaration, string $error): void
    {
        $libelle = $declaration->libelleNotification();
        $this->send(
            $declaration,
            "Spotlight : publication à reprendre pour {$libelle}",
            "La publication de {$libelle} n'a pas abouti. {$error} Le dossier reste en attente ; vérifiez-le avant de relancer."
        );
    }

    private function send(Declaration $declaration, string $subject, string $message): void
    {
        User::query()
            ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
            ->where('is_blocked', false)
            ->get()
            ->each(function (User $recipient) use ($declaration, $subject, $message) {
                try {
                    $notification = AppNotification::firstOrCreate(
                        [
                            'user_id' => $recipient->id,
                            'declaration_id' => $declaration->id,
                            'message' => $message,
                        ],
                        ['date_envoi' => now(), 'canal' => 'app']
                    );

                    if ($notification->wasRecentlyCreated) {
                        $recipient->notify(new ModerationActionRequired($declaration->id, $subject, $message));
                    }
                } catch (Throwable $exception) {
                    Log::error('Alerte moderation impossible Spotlight', [
                        'declaration_id' => $declaration->id,
                        'recipient_id' => $recipient->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });
    }
}
