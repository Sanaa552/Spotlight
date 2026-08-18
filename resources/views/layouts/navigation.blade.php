<nav x-data="{ open: false }" class="bg-nuit border-b border-white/10">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <x-spotlight-wordmark size="text-2xl hidden sm:block" />

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Tableau de bord') }}
                    </x-nav-link>

                    @auth
                        @if (auth()->user()->isCitoyen())
                            <x-nav-link :href="route('declarations.index')" :active="request()->routeIs('declarations.*')">
                                {{ __('Mes déclarations') }}
                            </x-nav-link>
                            <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                                {{ __('Notifications') }}
                            </x-nav-link>
                        @endif

                        @if (auth()->user()->isModerateur())
                            <x-nav-link :href="route('moderation.index')" :active="request()->routeIs('moderation.*')">
                                {{ __('Modération') }}
                            </x-nav-link>
                        @endif

                        @if (auth()->user()->isAdministrateur())
                            <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                {{ __('Utilisateurs') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.statistiques.index')" :active="request()->routeIs('admin.statistiques.*')">
                                {{ __('Statistiques') }}
                            </x-nav-link>
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
                {{ __('Tableau de bord') }}
            </x-responsive-nav-link>

            @auth
                @if (auth()->user()->isCitoyen())
                    <x-responsive-nav-link :href="route('declarations.index')" :active="request()->routeIs('declarations.*')">
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

        <!-- Responsive Settings Options -->
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
</nav>