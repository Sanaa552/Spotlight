<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\NewPublicDeclaration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnnouncePublishedDeclaration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $declarationId) {}

    public function handle(): void
    {
        $declaration = Declaration::publique()->whereKey($this->declarationId)
            ->whereIn('statut', ['validee', 'cloturee'])->first();

        if (! $declaration) {
            return;
        }

        $title = $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie);
        $message = "Nouvelle déclaration de {$declaration->type} #{$declaration->id} publiée : {$title}.";

        User::query()
            ->where('role', Role::Citoyen->value)
            ->where('is_blocked', false)
            ->whereNotNull('email_verified_at')
            ->where('id', '!=', $declaration->user_id)
            ->chunkById(100, function ($citizens) use ($declaration, $message, $title) {
                foreach ($citizens as $citizen) {
                    try {
                        $notification = AppNotification::firstOrCreate(
                            [
                                'user_id' => $citizen->id,
                                'declaration_id' => $declaration->id,
                                'message' => $message,
                            ],
                            ['date_envoi' => now(), 'canal' => 'app']
                        );

                        if ($notification->wasRecentlyCreated && $citizen->new_declaration_email) {
                            $citizen->notify(new NewPublicDeclaration(
                                $declaration->id,
                                $declaration->type,
                                $title,
                                $declaration->lieu,
                            ));
                        }
                    } catch (Throwable $exception) {
                        Log::error('Annonce de nouvelle declaration impossible Spotlight', [
                            'declaration_id' => $declaration->id,
                            'user_id' => $citizen->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }
}
