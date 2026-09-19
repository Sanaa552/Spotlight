<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\Declaration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ModerationConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $declarationId,
        public string $moderatorName,
        public string $moderatorEmail,
        public string $citizenName,
        public string $citizenEmail,
        public string $type,
        public string $category,
        public string $summary,
        public string $validatedAt,
        public bool $isPrivate,
        public ?string $facebookUrl = null,
        public ?string $instagramUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function sendFor(Declaration $declaration, User $moderator, bool $isPrivate = false): void
    {
        $declaration->loadMissing('citoyen');
        $notification = new self(
            $declaration->id,
            $moderator->name,
            $moderator->email,
            $declaration->citoyen->name,
            $declaration->citoyen->email,
            $declaration->type,
            $declaration->categorie,
            Str::limit($declaration->description, 500),
            now()->timezone('Africa/Douala')->format('d/m/Y H:i'),
            $isPrivate,
            $declaration->facebook_post_url,
            $declaration->instagram_post_url,
        );

        User::query()->where('role', Role::Administrateur->value)->each(
            fn (User $admin) => $admin->notify($notification)
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->isPrivate
                ? "Spotlight : signalement privé #{$this->declarationId} vérifié"
                : "Spotlight : déclaration #{$this->declarationId} publiée")
            ->greeting('Bonjour,')
            ->line($this->isPrivate
                ? "Le signalement #{$this->declarationId} a été vérifié par la modération. Il reste privé : aucune publication sur les réseaux sociaux."
                : "La déclaration #{$this->declarationId} a été publiée sur Facebook et Instagram.")
            ->line("Modération : {$this->moderatorName} ({$this->moderatorEmail})")
            ->line("Citoyen : {$this->citizenName} ({$this->citizenEmail})")
            ->line("Dossier : {$this->type} / {$this->category}")
            ->line("Résumé : {$this->summary}")
            ->line("Validation : {$this->validatedAt}");

        if ($this->facebookUrl) {
            $mail->line("Facebook : {$this->facebookUrl}");
        }
        if ($this->instagramUrl) {
            $mail->line("Instagram : {$this->instagramUrl}");
        }

        return $mail->action('Consulter le dossier', route('moderation.declarations.show', $this->declarationId))
            ->line('Les justificatifs privés restent uniquement dans Spotlight.');
    }
}
