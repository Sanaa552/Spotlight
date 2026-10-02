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
        $perte = $rapprochement->perte->libelleNotification();
        $decouverte = $rapprochement->decouverte->libelleNotification();
        $moderateurs = User::query()
            ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
            ->where('is_blocked', false)->get();

        $this->envoyer(
            $moderateurs,
            $rapprochement->decouverte_id,
            "Une correspondance a été proposée entre {$decouverte} (déclarant : {$rapprochement->decouverte->citoyen->name}) et {$perte} (déclarant : {$rapprochement->perte->citoyen->name}). Vérifiez les deux dossiers avant de contacter les déclarants.",
            "Spotlight : correspondance à vérifier pour {$perte}",
            route('moderation.declarations.show', $rapprochement->decouverte_id),
        );
    }

    public function decision(Rapprochement $rapprochement, bool $acceptee): void
    {
        $perte = $rapprochement->perte->libelleNotification();
        $decouverte = $rapprochement->decouverte->libelleNotification();
        if ($acceptee) {
            $messageProprietaire = "Une découverte pourrait correspondre à votre {$perte}. La modération a comparé les dossiers, mais l'objet n'est pas encore déclaré remis à son propriétaire. Vous recevrez les informations du poste après examen du justificatif ou confirmation directe du dépôt et publication de la découverte. Ne confirmez la restitution qu'après avoir récupéré l'objet.";
            $messageDecouvreur = "La modération a vérifié une correspondance possible pour votre {$decouverte}. Cela ne confirme pas une restitution. La publication attend encore le contrôle du dépôt auprès du poste ; conservez le récépissé.";
            $this->envoyer(collect([$rapprochement->perte->citoyen]), $rapprochement->perte_id,
                $messageProprietaire, "Spotlight : correspondance possible pour {$perte}", null, $rapprochement);
            if ($rapprochement->decouverte->user_id !== $rapprochement->perte->user_id) {
                $this->envoyer(collect([$rapprochement->decouverte->citoyen]), $rapprochement->decouverte_id,
                    $messageDecouvreur, "Spotlight : correspondance vérifiée pour {$decouverte}", null, $rapprochement);
            }

            return;
        }

        $this->envoyer(
            collect([$rapprochement->decouverte->citoyen]),
            $rapprochement->decouverte_id,
            "La correspondance proposée pour votre {$decouverte} n'a pas été retenue. Votre déclaration reste suivie séparément.",
            "Spotlight : correspondance non retenue pour {$decouverte}",
            null,
            $rapprochement,
        );
    }

    public function depotConfirme(Rapprochement $rapprochement): void
    {
        $perte = $rapprochement->perte->libelleNotification();
        $poste = $rapprochement->decouverte->poste_verifie_nom;
        $documentaire = $rapprochement->decouverte->depotDocumentaire();
        $this->envoyer(
            collect([$rapprochement->perte->citoyen]),
            $rapprochement->perte_id,
            $documentaire
                ? "La découverte liée à votre {$perte} a été publiée après examen d'un justificatif mentionnant : {$poste}. Spotlight n'a pas confirmé directement que ce poste détient encore l'objet. Contactez le poste avant de vous déplacer avec vos justificatifs. La restitution n'est pas confirmée ; confirmez-la dans Spotlight seulement après avoir récupéré l'objet."
                : "La découverte liée à votre {$perte} a été vérifiée et publiée. L'objet est signalé auprès de : {$poste}. Rapprochez-vous de ce poste avec vos justificatifs. Il est localisé, mais sa restitution n'est pas encore confirmée. Confirmez dans Spotlight seulement après l'avoir récupéré.",
            $documentaire ? "Spotlight : justificatif de dépôt examiné - {$perte}" : "Spotlight : votre objet est localisé - {$perte}",
            null,
            $rapprochement,
        );
    }

    public function remiseDeclareeParProprietaire(Rapprochement $rapprochement): void
    {
        $perte = $rapprochement->perte->libelleNotification();
        $decouverte = $rapprochement->decouverte->libelleNotification();
        $moderateurs = User::query()
            ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
            ->where('is_blocked', false)->get();

        $this->envoyer(
            $moderateurs,
            $rapprochement->decouverte_id,
            "Le propriétaire de {$perte} confirme avoir récupéré l'objet lié à {$decouverte}. Contrôlez la remise effective avant de clôturer et de publier un avis de restitution.",
            "Spotlight : remise à contrôler pour {$perte}",
            route('moderation.declarations.show', $rapprochement->decouverte_id),
        );
    }

    public function remiseDeclareeParDecouvreur(Rapprochement $rapprochement): void
    {
        $perte = $rapprochement->perte->libelleNotification();
        $decouverte = $rapprochement->decouverte->libelleNotification();

        if ($rapprochement->perte->user_id !== $rapprochement->decouverte->user_id) {
            $this->envoyer(
                collect([$rapprochement->perte->citoyen]),
                $rapprochement->perte_id,
                "Le découvreur de {$decouverte} signale une remise de l'objet lié à votre {$perte}. Cette déclaration n'est pas une restitution vérifiée. Confirmez dans votre dossier uniquement si vous avez effectivement récupéré l'objet ; la modération contrôlera ensuite la remise.",
                "Spotlight : remise signalée pour {$perte}",
                null,
                $rapprochement,
            );
        }

        $moderateurs = User::query()
            ->whereIn('role', [Role::Moderateur->value, Role::Administrateur->value])
            ->where('is_blocked', false)->get();
        $this->envoyer(
            $moderateurs,
            $rapprochement->decouverte_id,
            "Le découvreur de {$decouverte} signale une remise de l'objet lié à {$perte}. Ce signalement ne prouve pas la restitution. Attendez la confirmation du propriétaire, puis contrôlez la remise avant de clôturer les dossiers.",
            "Spotlight : remise signalée à contrôler pour {$perte}",
            route('moderation.declarations.show', $rapprochement->decouverte_id),
        );
    }

    public function restitution(Rapprochement $rapprochement): void
    {
        $perte = $rapprochement->perte->libelleNotification();
        $decouverte = $rapprochement->decouverte->libelleNotification();
        $this->envoyer(
            collect([$rapprochement->perte->citoyen, $rapprochement->decouverte->citoyen])->unique('id'),
            $rapprochement->decouverte_id,
            "La remise de l'objet lié à {$perte} et {$decouverte} a été confirmée par la modération. Ces dossiers figurent désormais dans les restitutions ; les avis Facebook et Instagram sont en cours d'envoi.",
            "Spotlight : restitution confirmée pour {$perte}",
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
