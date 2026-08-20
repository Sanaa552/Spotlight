<nav x-data="{ open: false }" class="bg-nuit border-b border-white/10 fixed top-0 inset-x-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0">
                    <x-spotlight-icon class="h-9 w-9" />
                    <x-spotlight-wordmark size="text-xl hidden sm:block" />
                </a>

                <!-- Icônes de navigation -->
                <div class="hidden sm:flex items-center gap-1">
                    <a href="{{ route('dashboard') }}" title="Accueil"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('dashboard') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/>
                        </svg>
                    </a>

                    @auth
                        @if (auth()->user()->isCitoyen())
                            <a href="{{ route('declarations.create') }}" title="Nouvelle déclaration"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('declarations.create') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                            </a>
                            <a href="{{ route('declarations.index') }}" title="Mes déclarations"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('declarations.index') || request()->routeIs('declarations.show') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </a>
                            <a href="{{ route('notifications.index') }}" title="Notifications"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('notifications.index') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </a>
                        @endif

                        @if (auth()->user()->isModerateur())
                            <a href="{{ route('moderation.index') }}" title="Modération"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('moderation.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </a>
                        @endif

                        @if (auth()->user()->isAdministrateur())
                            <a href="{{ route('admin.users.index') }}" title="Utilisateurs"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('admin.users.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 3.13a4 4 0 00-3-3.87"/>
                                </svg>
                            </a>
                            <a href="{{ route('admin.statistiques.index') }}" title="Statistiques"
                               class="p-2.5 rounded-full transition {{ request()->routeIs('admin.statistiques.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60 hover:text-argent hover:bg-white/5' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </a>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center text-sm font-medium text-argent/70 hover:text-argent focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>

                            @auth
                                <span @class([
                                    'ms-2 px-2 py-0.5 rounded-full text-xs font-semibold',
                                    'bg-azur/15 text-azur' => auth()->user()->isCitoyen(),
                                    'bg-laiton/15 text-laiton' => auth()->user()->isModerateur(),
                                    'bg-alerte/15 text-alerte' => auth()->user()->isAdministrateur(),
                                ])>
                                    {{ auth()->user()->role->value }}
                                </span>
                            @endauth

                            <svg class="ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profil') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Déconnexion') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-argent/60 hover:text-argent hover:bg-white/5 focus:outline-none focus:bg-white/5 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-nuit">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Accueil') }}
            </x-responsive-nav-link>

            @auth
                @if (auth()->user()->isCitoyen())
                    <x-responsive-nav-link :href="route('declarations.create')" :active="request()->routeIs('declarations.create')">
                        {{ __('Nouvelle déclaration') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('declarations.index')" :active="request()->routeIs('declarations.index')">
                        {{ __('Mes déclarations') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                        {{ __('Notifications') }}
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->isModerateur())
                    <x-responsive-nav-link :href="route('moderation.index')" :active="request()->routeIs('moderation.*')">
                        {{ __('Modération') }}
                    </x-responsive-nav-link>
                @endif

                @if (auth()->user()->isAdministrateur())
                    <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        {{ __('Utilisateurs') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.statistiques.index')" :active="request()->routeIs('admin.statistiques.*')">
                        {{ __('Statistiques') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        <div class="pt-4 pb-1 border-t border-white/10">
            <div class="px-4">
                <div class="font-medium text-base text-argent">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-argent/50">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profil') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Déconnexion') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>

        <!-- Barre d'icônes mobile (fixe en bas) -->
    <div class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-nuit border-t border-white/10">
        <div class="flex items-center justify-around py-2">
            <a href="{{ route('dashboard') }}" title="Accueil"
               class="p-2.5 rounded-full transition {{ request()->routeIs('dashboard') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/>
                </svg>
            </a>

            @auth
                @if (auth()->user()->isCitoyen())
                    <a href="{{ route('declarations.create') }}" title="Nouvelle déclaration"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('declarations.create') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </a>
                    <a href="{{ route('declarations.index') }}" title="Mes déclarations"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('declarations.index') || request()->routeIs('declarations.show') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </a>
                    <a href="{{ route('notifications.index') }}" title="Notifications"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('notifications.index') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </a>
                @endif

                @if (auth()->user()->isModerateur())
                    <a href="{{ route('moderation.index') }}" title="Modération"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('moderation.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </a>
                @endif

                @if (auth()->user()->isAdministrateur())
                    <a href="{{ route('admin.users.index') }}" title="Utilisateurs"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('admin.users.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 3.13a4 4 0 00-3-3.87"/>
                        </svg>
                    </a>
                    <a href="{{ route('admin.statistiques.index') }}" title="Statistiques"
                       class="p-2.5 rounded-full transition {{ request()->routeIs('admin.statistiques.*') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </a>
                @endif
            @endauth

            <a href="{{ route('profile.edit') }}" title="Profil"
               class="p-2.5 rounded-full transition {{ request()->routeIs('profile.edit') ? 'text-alerte bg-alerte/10' : 'text-argent/60' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </a>
        </div>
    </div>
</nav>