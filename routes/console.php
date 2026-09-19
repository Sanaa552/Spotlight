<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('spotlight:meta-status', function () {
    $appId = config('services.facebook.client_id');
    $appSecret = config('services.facebook.client_secret');
    $redirect = config('services.facebook.redirect');
    $pageId = config('services.meta.page_id');
    $pageToken = config('services.meta.page_access_token');
    $version = trim(config('services.meta.graph_version', 'v26.0'), '/');
    $verify = config('services.meta.ca_bundle') ?: true;

    $this->line('Facebook Login : '.(filled($appId) && filled($appSecret) ? 'configuré' : 'App ID ou App Secret manquant'));
    $this->line('URL de retour : '.($redirect ?: 'non définie'));
    if (blank($pageId) || blank($pageToken)) {
        $this->error('Publication Meta : identifiant Page ou jeton manquant.');

        return 1;
    }

    try {
        $page = Http::withOptions(['verify' => $verify])->withToken($pageToken)
            ->timeout(15)->get("https://graph.facebook.com/{$version}/{$pageId}", ['fields' => 'id']);
        if (! $page->successful()) {
            $this->error('Jeton refusé par Meta (HTTP '.$page->status().', code '.($page->json('error.code') ?: '?').').');

            return 1;
        }
        if ((string) $page->json('id') !== (string) $pageId) {
            $this->error('Meta ne confirme pas l’accès à META_PAGE_ID avec ce jeton.');

            return 1;
        }
        $this->info('Jeton accepté pour lire la Page configurée (la publication reste à tester séparément).');

        if (blank($appId) || blank($appSecret)) {
            $this->warn('Expiration non vérifiée : renseignez FACEBOOK_CLIENT_ID et FACEBOOK_CLIENT_SECRET.');

            return 0;
        }

        $debug = Http::withOptions(['verify' => $verify])->withToken($appId.'|'.$appSecret)
            ->timeout(15)->get("https://graph.facebook.com/{$version}/debug_token", ['input_token' => $pageToken]);
        if (! $debug->successful() || ! $debug->json('data.is_valid')) {
            $this->warn('Meta n’a pas confirmé la durée du jeton pour cette application (HTTP '.$debug->status().').');

            return 0;
        }

        $expiresAt = (int) $debug->json('data.expires_at', 0);
        $this->line('Type du jeton : '.($debug->json('data.type') ?: 'non communiqué'));
        $this->line('Expiration : '.($expiresAt
            ? Carbon::createFromTimestamp($expiresAt)->timezone('Africa/Douala')->format('d/m/Y H:i')
            : 'aucune date annoncée par Meta (jeton toujours révocable)'));
        if ($expiresAt && $expiresAt < now()->addDays(7)->timestamp) {
            $this->warn('Le jeton expire dans moins de 7 jours : prévoyez son remplacement.');
        }
    } catch (\Throwable $exception) {
        $this->error('Meta est inaccessible depuis ce terminal : '.$exception::class);

        return 1;
    }

    return 0;
})->purpose('Contrôler la configuration Meta sans révéler les secrets');
