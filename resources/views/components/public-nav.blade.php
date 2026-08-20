<header class="border-b border-white/10 bg-nuit fixed top-0 inset-x-0 z-40">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2">
            <x-spotlight-icon class="h-9 w-9" />
            <x-spotlight-wordmark size="text-xl" />
        </a>

        <nav class="hidden sm:flex items-center gap-6">
            <a href="/" class="text-sm {{ request()->is('/') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                Accueil
            </a>
            <a href="{{ route('public.declarations.index') }}" class="text-sm {{ request()->routeIs('public.declarations.index') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                Voir les avis
            </a>
            <a href="{{ route('public.about') }}" class="text-sm {{ request()->routeIs('public.about') ? 'text-argent font-semibold' : 'text-argent/60 hover:text-argent' }} transition">
                À propos
            </a>
            <a href="{{ route('declarations.create') }}" class="text-sm text-argent/60 hover:text-argent transition">
                Ajouter une déclaration
            </a>
        </nav>

        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-5 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    Tableau de bord
                </a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-medium text-argent/70 hover:text-argent transition">
                    Se connecter
                </a>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center px-5 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    Créer un compte
                </a>
            @endauth
        </div>
    </div>
</header>

<div class="h-[73px]"></div>