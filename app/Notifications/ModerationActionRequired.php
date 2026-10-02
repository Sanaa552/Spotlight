<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ModerationActionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public string $subject,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting('Bonjour,')
            ->line($this->message)
            ->line('Vérifiez les justificatifs privés dans Spotlight avant toute décision ou publication.')
            ->action('Examiner le dossier', route('moderation.declarations.show', $this->declarationId));
    }
}
