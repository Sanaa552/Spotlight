<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public string $authorName,
        public string $content,
        public bool $isReply = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->isReply ? 'Nouvelle réponse' : 'Nouvelle information')." sur la déclaration #{$this->declarationId}")
            ->greeting('Bonjour,')
            ->line("{$this->authorName} ".($this->isReply ? 'a répondu dans la discussion' : 'a partagé une information')." sur la déclaration #{$this->declarationId} :")
            ->line($this->content)
            ->action('Voir les informations', route('public.declarations.show', $this->declarationId).'#discussion')
            ->line('Si ce message est inapproprié, signalez-le à la modération.');
    }
}
