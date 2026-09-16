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

        return [
            ...$this->postToGraph('instagram', 'media_publish', $this->instagramId, [
                'creation_id' => $container['response']['id'],
            ]),
            'step' => 'publish_container',
            'container_id' => $container['response']['id'],
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
                ->timeout(20)
                ->retry(2, 500)
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
