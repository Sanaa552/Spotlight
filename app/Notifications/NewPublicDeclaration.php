<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPublicDeclaration extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public string $type,
        public string $title,
        public ?string $lieu,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nouvel avis Spotlight : {$this->title}")
            ->greeting('Bonjour,')
            ->line("Une nouvelle déclaration de {$this->type} a été publiée sur Spotlight.")
            ->line($this->title)
            ->line($this->lieu ? "Secteur : {$this->lieu}" : 'Consultez les détails de cet avis sur Spotlight.')
            ->action('Voir la déclaration', route('public.declarations.show', $this->declarationId))
            ->line('Vous pouvez désactiver ces emails depuis votre profil Spotlight.');
    }
}
