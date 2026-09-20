<?php

namespace App\Jobs;

use App\Models\Declaration;
use App\Models\PublicationReminder;
use App\Services\MetaPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublishReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(public int $reminderId) {}

    public function handle(MetaPublishingService $meta): void
    {
        $claimed = PublicationReminder::query()->whereKey($this->reminderId)
            ->where('status', 'queued')->update(['status' => 'processing']);
        if (! $claimed) {
            return;
        }

        $reminder = PublicationReminder::findOrFail($this->reminderId);
        try {
            $declaration = Declaration::findOrFail($reminder->declaration_id);
            if (! in_array($declaration->statut, ['validee', 'cloturee'], true)
                || ! Declaration::publique()->whereKey($declaration->id)->exists()
                || ! Storage::disk('public')->exists($declaration->photo_path)) {
                throw new RuntimeException('La déclaration ou sa photo publique ne sont plus disponibles.');
            }

            if (! $reminder->post_id) {
                $message = "RAPPEL\n\n".$declaration->publicationMessage();
                if ($reminder->channel === 'facebook') {
                    $result = $meta->publishToFacebook($message, $declaration->photoUrl());
                } else {
                    $source = $meta->facebookPhotoUrl($declaration->facebook_post_id);
                    $result = $source['success']
                        ? $meta->publishToInstagram($source['url'], $message)
                        : $source;
                }

                $postId = $result['response']['id'] ?? null;
                if (! ($result['success'] ?? false) || ! $postId) {
                    Log::warning('Rappel Meta non confirme Spotlight', [
                        'reminder_id' => $reminder->id,
                        'declaration_id' => $declaration->id,
                        'channel' => $reminder->channel,
                        'result' => $result,
                    ]);
                    $reminder->update([
                        'status' => 'failed',
                        'error' => ($result['response']['error']['code'] ?? null) === 190
                            ? 'Connexion Meta expirée. Demandez à l’administrateur de renouveler le jeton.'
                            : 'Meta n’a pas confirmé le rappel. Vérifiez les journaux avant de réessayer.',
                    ]);

                    return;
                }
                $reminder->update(['post_id' => $postId]);
            }

            $url = $meta->publicPostUrl($reminder->channel, $reminder->post_id);
            $reminder->update([
                'status' => 'succeeded',
                'post_url' => $url,
                'error' => null,
            ]);
            Log::info('Rappel Meta publie Spotlight', [
                'reminder_id' => $reminder->id,
                'declaration_id' => $declaration->id,
                'channel' => $reminder->channel,
                'post_id' => $reminder->post_id,
                'has_url' => filled($url),
            ]);
        } catch (Throwable $exception) {
            Log::error('Echec technique rappel Meta Spotlight', [
                'reminder_id' => $reminder->id,
                'declaration_id' => $reminder->declaration_id,
                'channel' => $reminder->channel,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $reminder->update([
                'status' => 'failed',
                'error' => 'Le rappel n’a pas abouti. Contactez l’administrateur si le problème persiste.',
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        PublicationReminder::query()->whereKey($this->reminderId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'error' => 'Le traitement du rappel a été interrompu. Vérifiez les journaux.',
            ]);
        Log::error('Job rappel Meta interrompu Spotlight', [
            'reminder_id' => $this->reminderId,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);
    }
}
