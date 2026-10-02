<header class="border-b border-white/10 bg-nuit fixed top-0 inset-x-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-2">
        <a href="/" class="flex items-center gap-2">
            <x-spotlight-icon class="h-9 w-9" />
            <x-spotlight-wordmark size="hidden sm:inline text-xl" />
        </a>

        <nav class="hidden lg:flex items-center gap-6">
            <a href="/" class="text-sm {{ request()->is('/') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                Accueil
            </a>
            <a href="{{ route('public.declarations.index') }}" class="text-sm {{ request()->routeIs('public.declarations.index') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                Voir les avis
            </a>
            <a href="{{ route('public.about') }}" class="text-sm {{ request()->routeIs('public.about') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                À propos
            </a>
            @guest
                <a href="{{ route('register') }}" class="text-sm text-argent/60 hover:text-argent transition">Ajouter une déclaration</a>
            @else
                @if (auth()->user()->needsFacebookProfileCompletion())
                    <a href="{{ route('facebook.profile.edit') }}" class="text-sm text-argent/60 hover:text-argent transition">Compléter mon profil</a>
                @elseif (! auth()->user()->hasVerifiedEmail())
                    <a href="{{ route('verification.notice') }}" class="text-sm text-argent/60 hover:text-argent transition">Vérifier mon e-mail</a>
                @elseif (auth()->user()->isCitoyen() && ! auth()->user()->is_blocked)
                    <a href="{{ route('declarations.create') }}" class="text-sm text-argent/60 hover:text-argent transition">Ajouter une déclaration</a>
                @elseif (! auth()->user()->is_blocked)
                    <a href="{{ route('moderation.index') }}" class="text-sm text-argent/60 hover:text-argent transition">Modération</a>
                @endif
            @endguest
        </nav>

        <div class="flex items-center gap-1 sm:gap-3">
            <x-theme-toggle />
            @auth
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-5 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    {{ auth()->user()->isCitoyen() ? 'Mon espace' : (auth()->user()->isAdministrateur() ? 'Dashboard' : 'Tableau de bord') }}
                </a>
            @else
                <a href="{{ route('login') }}" class="text-xs sm:text-sm font-medium text-argent/70 hover:text-argent transition">
                    Se connecter
                </a>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center px-2 sm:px-5 py-2 bg-alerte border border-transparent rounded-md font-semibold text-[11px] sm:text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    Créer un compte
                </a>
            @endauth
        </div>
    </div>
</header>

<div class="h-[73px]"></div>
