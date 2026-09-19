<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Rapprochement;
use App\Models\User;
use App\Notifications\RapprochementUpdate;
use Illuminate\Support\Facades\Log;
use Throwable;

class RapprochementNotifier
{
    public function proposition(Rapprochement $rapprochement): void
    {
        $moderateurs = User::query()
            ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
            ->where('is_blocked', false)->get();

        $this->envoyer(
            $moderateurs,
            $rapprochement->decouverte_id,
            "Une correspondance a été proposée entre la découverte #{$rapprochement->decouverte_id} et la perte #{$rapprochement->perte_id}. Vérifiez les deux dossiers avant de contacter les déclarants.",
            'Correspondance à vérifier - Spotlight',
            route('moderation.declarations.show', $rapprochement->decouverte_id),
        );
    }

    public function decision(Rapprochement $rapprochement, bool $acceptee): void
    {
        $this->envoyer(
            collect([$rapprochement->decouverte->citoyen, ...($acceptee ? [$rapprochement->perte->citoyen] : [])])->unique('id'),
            $rapprochement->decouverte_id,
            $acceptee
                ? "La modération a vérifié une correspondance possible entre les dossiers #{$rapprochement->perte_id} et #{$rapprochement->decouverte_id}. La remise doit être organisée avec les autorités ; confirmez-la seulement après qu'elle a réellement eu lieu."
                : "La correspondance proposée pour votre découverte #{$rapprochement->decouverte_id} n'a pas été retenue. Votre déclaration reste suivie séparément.",
            $acceptee ? 'Correspondance vérifiée - Spotlight' : 'Correspondance non retenue - Spotlight',
            null,
            $rapprochement,
        );
    }

    public function restitution(Rapprochement $rapprochement): void
    {
        $this->envoyer(
            collect([$rapprochement->perte->citoyen, $rapprochement->decouverte->citoyen])->unique('id'),
            $rapprochement->decouverte_id,
            "La restitution liée aux dossiers #{$rapprochement->perte_id} et #{$rapprochement->decouverte_id} a été confirmée par la modération. Ces dossiers figurent désormais dans les restitutions.",
            'Restitution confirmée - Spotlight',
            null,
            $rapprochement,
        );
    }

    private function envoyer($destinataires, int $declarationId, string $message, string $sujet, ?string $url = null, ?Rapprochement $rapprochement = null): void
    {
        foreach ($destinataires as $destinataire) {
            if (! $destinataire || $destinataire->is_blocked) {
                continue;
            }

            $lien = $url ?? route('declarations.show',
                $destinataire->id === $rapprochement?->perte->user_id ? $rapprochement->perte_id : $rapprochement->decouverte_id);

            try {
                AppNotification::create([
                    'user_id' => $destinataire->id,
                    'declaration_id' => $rapprochement && $destinataire->id === $rapprochement->perte->user_id
                        ? $rapprochement->perte_id : $declarationId,
                    'message' => $message,
                    'date_envoi' => now(),
                    'canal' => 'app',
                ]);
                $destinataire->notify(new RapprochementUpdate($sujet, $message, $lien));
            } catch (Throwable $exception) {
                Log::error('Notification rapprochement impossible Spotlight', [
                    'declaration_id' => $declarationId,
                    'user_id' => $destinataire->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
