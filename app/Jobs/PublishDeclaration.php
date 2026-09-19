<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\Declaration;
use App\Models\User;
use App\Notifications\DeclarationPublished;
use App\Notifications\ModerationConfirmed;
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

class PublishDeclaration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(
        public int $declarationId,
        public int $moderateurId,
    ) {}

    public function handle(MetaPublishingService $meta): void
    {
        $claimed = Declaration::query()
            ->whereKey($this->declarationId)
            ->where('statut', 'en_attente')
            ->where('publication_status', 'queued')
            ->update(['publication_status' => 'processing']);

        if (! $claimed) {
            return;
        }

        Log::info('Traitement publication demarre Spotlight', [
            'declaration_id' => $this->declarationId,
            'moderateur_id' => $this->moderateurId,
        ]);

        $declaration = Declaration::findOrFail($this->declarationId);

        try {
            if ($declaration->photoEnAttente()) {
                $this->rendrePhotoPublique($declaration);
            }

            if (! $declaration->photo_path || ! Storage::disk('public')->exists($declaration->photo_path)) {
                throw new RuntimeException('Photo publique introuvable.');
            }

            $imageUrl = $declaration->photoUrl();
            $message = $declaration->publicationMessage();

            if (! $declaration->facebook_post_id) {
                $result = $meta->publishToFacebook($message, $imageUrl);
                Log::log($result['success'] ? 'info' : 'warning', 'Publication Facebook Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $this->moderateurId,
                    'result' => $result,
                ]);

                $id = $result['response']['id'] ?? null;
                if (! $result['success'] || ! $id) {
                    $this->marquerEchec($declaration, 'Facebook', $result);
                    return;
                }

                $declaration->update(['facebook_post_id' => $id]);
            }

            if (! $declaration->instagram_post_id) {
                $photoSource = $meta->facebookPhotoUrl($declaration->facebook_post_id);
                if (! $photoSource['success']) {
                    Log::warning('Photo Facebook inaccessible pour Instagram Spotlight', [
                        'declaration_id' => $declaration->id,
                        'moderateur_id' => $this->moderateurId,
                        'result' => $photoSource,
                    ]);
                    $this->marquerEchec($declaration, 'Instagram', $photoSource);
                    return;
                }

                Log::info('Photo Facebook confirmee pour Instagram Spotlight', [
                    'declaration_id' => $declaration->id,
                    'facebook_photo_id' => $declaration->facebook_post_id,
                    'status' => $photoSource['status'] ?? null,
                    'image_host' => parse_url($photoSource['url'], PHP_URL_HOST),
                ]);

                $result = $meta->publishToInstagram($photoSource['url'], $message);
                Log::log($result['success'] ? 'info' : 'warning', 'Publication Instagram Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $this->moderateurId,
                    'result' => $result,
                ]);

                $id = $result['response']['id'] ?? null;
                if (! $result['success'] || ! $id) {
                    $this->marquerEchec($declaration, 'Instagram', $result);
                    return;
                }

                $declaration->update(['instagram_post_id' => $id]);
            }

            $declaration->publier();
            $declaration->update([
                'moderateur_id' => $this->moderateurId,
                'publication_status' => 'succeeded',
                'publication_error' => null,
            ]);

            foreach (['facebook', 'instagram'] as $channel) {
                $column = $channel.'_post_url';
                if (! $declaration->{$column}) {
                    $url = $meta->publicPostUrl($channel, $declaration->{$channel.'_post_id'});
                    if ($url) {
                        $declaration->update([$column => $url]);
                    }
                }
            }

            try {
                AppNotification::query()
                    ->where('declaration_id', $declaration->id)
                    ->where('user_id', $declaration->user_id)
                    ->where('message', 'like', 'La publication de votre déclaration%')
                    ->delete();
                $this->notifierCitoyen($declaration, "Votre déclaration #{$declaration->id} a été publiée sur Facebook, Instagram et Spotlight. Retrouvez les liens de partage dans votre dossier.");
                $declaration->citoyen->notify(new DeclarationPublished(
                    $declaration->id,
                    $declaration->facebook_post_url,
                    $declaration->instagram_post_url,
                ));
            } catch (Throwable $exception) {
                Log::error('Notification succes publication impossible Spotlight', [
                    'declaration_id' => $declaration->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
            try {
                ModerationConfirmed::sendFor($declaration, User::findOrFail($this->moderateurId));
                Log::info('Administrateurs informes de publication Spotlight', [
                    'declaration_id' => $declaration->id,
                    'moderateur_id' => $this->moderateurId,
                ]);
            } catch (Throwable $exception) {
                Log::error('Notification administrateur impossible Spotlight', [
                    'declaration_id' => $declaration->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
            try {
                AnnouncePublishedDeclaration::dispatch($declaration->id);
            } catch (Throwable $exception) {
                Log::error('Annonce de declaration non planifiee Spotlight', [
                    'declaration_id' => $declaration->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
            Log::info('Declaration validee apres publications Meta Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $this->moderateurId,
                'facebook_post_id' => $declaration->facebook_post_id,
                'instagram_post_id' => $declaration->instagram_post_id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Echec technique publication declaration Spotlight', [
                'declaration_id' => $declaration->id,
                'moderateur_id' => $this->moderateurId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            if ($declaration->fresh()->statut === 'en_attente') {
                $this->marquerEchec($declaration, 'technique');
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $declaration = Declaration::find($this->declarationId);
        if (! $declaration || $declaration->statut !== 'en_attente'
            || ! in_array($declaration->publication_status, ['queued', 'processing'], true)) {
            return;
        }

        Log::error('Job publication interrompu Spotlight', [
            'declaration_id' => $this->declarationId,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);
        $this->marquerEchec($declaration, 'technique');
    }

    private function marquerEchec(Declaration $declaration, string $channel, array $result = []): void
    {
        $status = $result['status'] ?? null;
        $code = $result['response']['error']['code'] ?? null;
        $details = array_filter([
            $status ? "HTTP {$status}" : null,
            $code ? "code Meta {$code}" : null,
        ]);
        if ($code === 190) {
            $error = 'Connexion Meta expirée. Demandez à l’administrateur de renouveler le jeton d’accès.';
        } elseif ($channel === 'technique') {
            $error = 'Erreur technique. Consultez les journaux et contactez l’administrateur.';
        } else {
            $error = "Publication {$channel} non confirmée".($details ? ' ('.implode(', ', $details).')' : '').'.';
        }

        $declaration->update([
            'publication_status' => 'failed',
            'publication_error' => $error,
        ]);

        Log::warning('Publication en attente de reprise par moderation Spotlight', [
            'declaration_id' => $declaration->id,
            'channel' => $channel,
            'facebook_post_id' => $declaration->facebook_post_id,
            'instagram_post_id' => $declaration->instagram_post_id,
        ]);
    }

    private function notifierCitoyen(Declaration $declaration, string $message): void
    {
        AppNotification::create([
            'user_id' => $declaration->user_id,
            'declaration_id' => $declaration->id,
            'message' => $message,
            'date_envoi' => now(),
            'canal' => 'app',
        ]);
    }

    private function rendrePhotoPublique(Declaration $declaration): void
    {
        $privatePath = $declaration->photo_path;
        if (! Storage::disk('local')->exists($privatePath)) {
            throw new RuntimeException('Photo en attente introuvable.');
        }

        $publicPath = 'photos-publiques/'.basename($privatePath);
        if (Storage::disk('public')->exists($publicPath)) {
            throw new RuntimeException('Une photo publique porte déjà ce nom.');
        }

        $stream = Storage::disk('local')->readStream($privatePath);
        if (! $stream) {
            throw new RuntimeException('Lecture de la photo en attente impossible.');
        }

        try {
            if (! Storage::disk('public')->put($publicPath, $stream)) {
                throw new RuntimeException('Publication de la photo impossible.');
            }
        } finally {
            fclose($stream);
        }

        try {
            if (Storage::disk('public')->size($publicPath) !== Storage::disk('local')->size($privatePath)) {
                throw new RuntimeException('Copie de la photo incomplète.');
            }
            $declaration->update(['photo_path' => $publicPath]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($publicPath);
            throw $exception;
        }

        if (! Storage::disk('local')->delete($privatePath)) {
            Log::warning('Ancienne photo en attente non supprimee Spotlight', [
                'declaration_id' => $declaration->id,
                'path' => $privatePath,
            ]);
        }
    }
}
