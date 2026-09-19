<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaPublishingService
{
    protected ?string $graphVersion;

    protected ?string $pageId;

    protected ?string $pageAccessToken;

    protected ?string $instagramId;

    public function __construct()
    {
        $this->graphVersion = config('services.meta.graph_version', 'v26.0');
        $this->pageId = config('services.meta.page_id');
        $this->pageAccessToken = config('services.meta.page_access_token');
        $this->instagramId = config('services.meta.instagram_id');
    }

    public function publishToFacebook(string $message, ?string $imageUrl = null): array
    {
        if ($missing = $this->missingConfig(['page_id', 'page_access_token'])) {
            return $this->configurationError('facebook', $missing);
        }

        $edge = $imageUrl ? 'photos' : 'feed';
        $payload = $imageUrl
            ? ['url' => $imageUrl, 'caption' => $message]
            : ['message' => $message];

        return $this->postToGraph('facebook', $edge, $this->pageId, $payload);
    }

    public function facebookPhotoUrl(string $photoId): array
    {
        if ($missing = $this->missingConfig(['page_access_token'])) {
            return $this->configurationError('facebook', $missing);
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s',
            trim($this->graphVersion ?: 'v26.0', '/'),
            $photoId
        );

        try {
            $response = Http::withOptions(['verify' => config('services.meta.ca_bundle') ?: true])
                ->withToken($this->pageAccessToken)
                ->timeout(20)
                ->get($endpoint, ['fields' => 'images']);
            $url = $response->json('images.0.source');

            return [
                'success' => $response->successful() && is_string($url) && $this->isPublicHttpsUrl($url),
                'channel' => 'facebook',
                'status' => $response->status(),
                'url' => $url,
                'response' => $response->successful() ? null : $response->json(),
            ];
        } catch (Throwable $exception) {
            Log::error('Lecture photo Facebook Spotlight impossible', [
                'photo_id' => $photoId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'channel' => 'facebook',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function publicPostUrl(string $channel, string $postId): ?string
    {
        if (! in_array($channel, ['facebook', 'instagram'], true) || blank($this->pageAccessToken)) {
            return null;
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s',
            trim($this->graphVersion ?: 'v26.0', '/'),
            $postId
        );
        $field = $channel === 'facebook' ? 'link' : 'permalink';

        try {
            $response = Http::withOptions(['verify' => config('services.meta.ca_bundle') ?: true])
                ->withToken($this->pageAccessToken)
                ->timeout(15)
                ->get($endpoint, ['fields' => $field]);
            $url = $response->json($field);

            if ($response->successful() && is_string($url) && $this->isPublicHttpsUrl($url)) {
                return $url;
            }

            Log::warning('Lien publication Meta indisponible Spotlight', [
                'channel' => $channel,
                'post_id' => $postId,
                'status' => $response->status(),
                'code' => $response->json('error.code'),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Lecture lien publication Meta impossible Spotlight', [
                'channel' => $channel,
                'post_id' => $postId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    public function publishToInstagram(string $imageUrl, string $caption): array
    {
        if ($missing = $this->missingConfig(['instagram_id', 'page_access_token'])) {
            return $this->configurationError('instagram', $missing);
        }

        if (! $this->isPublicHttpsUrl($imageUrl)) {
            return [
                'success' => false,
                'channel' => 'instagram',
                'step' => 'validate_image_url',
                'error' => 'Instagram exige une image publique accessible en HTTPS.',
                'image_url' => $imageUrl,
            ];
        }

        $container = $this->postToGraph('instagram', 'media', $this->instagramId, [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ]);

        if (! $container['success'] || empty($container['response']['id'])) {
            return [
                ...$container,
                'step' => 'create_container',
            ];
        }

        $containerId = $container['response']['id'];
        $status = $this->waitForInstagramContainer($containerId);
        if (! $status['success']) {
            return [
                ...$status,
                'step' => 'wait_container',
                'container_id' => $containerId,
            ];
        }

        return [
            ...$this->postToGraph('instagram', 'media_publish', $this->instagramId, [
                'creation_id' => $containerId,
            ]),
            'step' => 'publish_container',
            'container_id' => $containerId,
        ];
    }

    private function waitForInstagramContainer(string $containerId): array
    {
        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s',
            trim($this->graphVersion ?: 'v26.0', '/'),
            $containerId
        );

        for ($attempt = 0; $attempt < 10; $attempt++) {
            if ($attempt > 0) {
                sleep(2);
            }

            try {
                $response = Http::withOptions(['verify' => config('services.meta.ca_bundle') ?: true])
                    ->withToken($this->pageAccessToken)
                    ->timeout(15)
                    ->get($endpoint, ['fields' => 'status_code,status']);
            } catch (Throwable $exception) {
                return [
                    'success' => false,
                    'channel' => 'instagram',
                    'error' => $exception->getMessage(),
                ];
            }

            $state = $response->json('status_code');
            if (! $response->successful() || in_array($state, ['ERROR', 'EXPIRED'], true)) {
                return [
                    'success' => false,
                    'channel' => 'instagram',
                    'status' => $response->status(),
                    'response' => $response->json(),
                ];
            }
            if ($state === 'FINISHED') {
                return ['success' => true, 'channel' => 'instagram'];
            }
        }

        return [
            'success' => false,
            'channel' => 'instagram',
            'error' => 'Le média Instagram n\'est pas prêt après 20 secondes.',
        ];
    }

    private function postToGraph(string $channel, string $edge, string $nodeId, array $payload): array
    {
        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/%s',
            trim($this->graphVersion ?: 'v26.0', '/'),
            $nodeId,
            $edge
        );

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => config('services.meta.ca_bundle') ?: true])
                ->timeout(20)
                ->post($endpoint, [
                    ...$payload,
                    'access_token' => $this->pageAccessToken,
                ]);

            return [
                'success' => $response->successful(),
                'channel' => $channel,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json() ?? ['body' => $response->body()],
            ];
        } catch (Throwable $exception) {
            Log::error('Erreur HTTP Meta Spotlight', [
                'channel' => $channel,
                'endpoint' => $endpoint,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'channel' => $channel,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function missingConfig(array $keys): array
    {
        $values = [
            'page_id' => $this->pageId,
            'page_access_token' => $this->pageAccessToken,
            'instagram_id' => $this->instagramId,
        ];

        return array_values(array_filter($keys, fn (string $key) => blank($values[$key] ?? null)));
    }

    private function configurationError(string $channel, array $missing): array
    {
        return [
            'success' => false,
            'channel' => $channel,
            'step' => 'configuration',
            'error' => 'Configuration Meta incomplete.',
            'missing' => $missing,
        ];
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && filled($parts['host'] ?? null)
            && ! in_array($parts['host'], ['localhost', '127.0.0.1'], true);
    }
}
