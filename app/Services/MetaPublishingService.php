<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MetaPublishingService
{
    protected string $pageId;
    protected string $pageAccessToken;
    protected string $instagramId;

    public function __construct()
    {
        $this->pageId = env('META_PAGE_ID');
        $this->pageAccessToken = env('META_PAGE_ACCESS_TOKEN');
        $this->instagramId = env('META_INSTAGRAM_ID');
    }

    public function publishToFacebook(string $message, ?string $imageUrl = null): array
{
    if ($imageUrl) {
        $response = Http::post(
            "https://graph.facebook.com/{$this->pageId}/photos",
            [
                'url' => $imageUrl,
                'caption' => $message,
                'access_token' => $this->pageAccessToken,
            ]
        );
    } else {
        $response = Http::post(
            "https://graph.facebook.com/{$this->pageId}/feed",
            [
                'message' => $message,
                'access_token' => $this->pageAccessToken,
            ]
        );
    }

    return $response->json();
}

    public function publishToInstagram(string $imageUrl, string $caption): array
{
    // 1. Créer le conteneur média
    $containerResponse = Http::post(
        "https://graph.facebook.com/{$this->instagramId}/media",
        [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->pageAccessToken,
        ]
    );

    $container = $containerResponse->json();

    if (!isset($container['id'])) {
        return [
            'success' => false,
            'step' => 'create_container',
            'response' => $container,
        ];
    }

    // 2. Publier le conteneur
    $publishResponse = Http::post(
        "https://graph.facebook.com/{$this->instagramId}/media_publish",
        [
            'creation_id' => $container['id'],
            'access_token' => $this->pageAccessToken,
        ]
    );

    return [
        'success' => $publishResponse->successful(),
        'response' => $publishResponse->json(),
    ];
}

}