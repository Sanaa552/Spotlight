<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeclarationModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public bool $accepted,
        public ?string $reason = null,
        public ?string $declarationLabel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->declarationLabel ?? "déclaration #{$this->declarationId}";
        $mail = (new MailMessage)
            ->subject($this->accepted
                ? "Spotlight : {$label} vérifié"
                : "Spotlight : décision concernant {$label}")
            ->greeting('Bonjour,')
            ->line($this->accepted
                ? "Le dossier {$label} a été vérifié par la modération. Il reste privé et n'est pas publié sur les réseaux sociaux."
                : "Le dossier {$label} n'a pas été validé par la modération.");

        if (! $this->accepted && $this->reason) {
            $mail->line("Motif : {$this->reason}");
        }

        return $mail->action('Consulter mon dossier', route('declarations.show', $this->declarationId));
    }
}
