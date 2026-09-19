<?php

namespace App\Providers;

use App\Models\Declaration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::defaultStringLength(191);

        if (! $this->app->runningInConsole()) {
            $host = request()->getHost();

            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                Vite::useHotFile(storage_path('framework/vite-hot-remote'));
            }

            if ($host === parse_url(config('app.url'), PHP_URL_HOST)
                && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
                URL::forceScheme('https');
            }
        }

        View::composer('layouts.navigation', function ($view) {
            $notificationsNonLues = 0;
            $declarationsEnAttente = 0;

            if (auth()->check()) {
                $notificationsNonLues = auth()->user()->appNotifications()->where('lu', false)->count();

                if (auth()->user()->isModerateur() || auth()->user()->isAdministrateur()) {
                    $declarationsEnAttente = Declaration::where('statut', 'en_attente')->count();
                }
            }

            $view->with([
                'notificationsNonLues' => $notificationsNonLues,
                'declarationsEnAttente' => $declarationsEnAttente,
            ]);
        });
    }
}
