<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DiscoveryProofRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public string $declarationLabel,
        public bool $isObject,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Spotlight : justificatif demandé pour {$this->declarationLabel}")
            ->greeting('Bonjour,')
            ->line("La modération a examiné votre dossier {$this->declarationLabel}, mais le justificatif des autorités manque encore.")
            ->line($this->isObject
                ? "Après avoir remis l'objet à un poste de police ou de gendarmerie, ajoutez le récépissé ou document délivré par ce poste."
                : 'Après avoir signalé la situation aux autorités, ajoutez le document qui confirme ce signalement.')
            ->line('Ouvrez votre dossier dans Spotlight pour ajouter ce document privé. Il ne sera pas publié sur les réseaux sociaux.')
            ->line('Votre dossier reste en attente ; aucune publication ne sera effectuée avant sa vérification.')
            ->action('Ajouter le justificatif', route('declarations.show', $this->declarationId));
    }
}
