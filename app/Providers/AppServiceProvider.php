<?php

namespace App\Providers;

use App\Models\Declaration;
use Illuminate\Database\Schema\Builder;
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
        View::composer('layouts.navigation', function ($view) {
            $notificationsNonLues = 0;
            $declarationsEnAttente = 0;

            if (auth()->check()) {
                if (auth()->user()->isCitoyen()) {
                    $notificationsNonLues = auth()->user()->appNotifications()->where('lu', false)->count();
                }

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
