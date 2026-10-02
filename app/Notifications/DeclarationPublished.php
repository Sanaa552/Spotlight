<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeclarationPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public ?string $facebookUrl,
        public ?string $instagramUrl,
        public ?string $declarationLabel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'notifiable' => $notifiable,
            'declarationId' => $this->declarationId,
            'declarationLabel' => $this->declarationLabel ?? "déclaration #{$this->declarationId}",
            'declarationUrl' => route('declarations.show', $this->declarationId),
            'facebookUrl' => $this->facebookUrl,
            'instagramUrl' => $this->instagramUrl,
        ];

        return (new MailMessage)
            ->subject('Spotlight : publication confirmée - '.($this->declarationLabel ?? "déclaration #{$this->declarationId}"))
            ->view([
                'html' => 'emails.declaration-published',
                'text' => 'emails.declaration-published-text',
            ], $data);
    }
}
