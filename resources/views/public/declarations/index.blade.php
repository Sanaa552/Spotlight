<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Spotlight') }} — Avis de recherche</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-nuit">

    <x-public-nav />

    <div class="h-[73px]"></div>

    <main class="px-6 py-10 max-w-6xl mx-auto">

        <div class="mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-bold text-argent">Avis de recherche</h1>
            <p class="text-sm text-argent/50 mt-1">Déclarations vérifiées par nos modérateurs</p>
        </div>

        <!-- Onglets -->
        <div class="flex justify-center gap-2 mb-10">
            <a href="{{ route('public.declarations.index', ['onglet' => 'pertes']) }}"
               class="px-5 py-2 rounded-full text-sm font-semibold transition {{ $onglet === 'pertes' ? 'bg-alerte text-white' : 'bg-white/5 text-argent/60 hover:bg-white/10' }}">
                🔍 Pertes
            </a>
            <a href="{{ route('public.declarations.index', ['onglet' => 'decouvertes']) }}"
               class="px-5 py-2 rounded-full text-sm font-semibold transition {{ $onglet === 'decouvertes' ? 'bg-sonar text-white' : 'bg-white/5 text-argent/60 hover:bg-white/10' }}">
                📢 Découvertes
            </a>
            <a href="{{ route('public.declarations.index', ['onglet' => 'restitutions']) }}"
               class="px-5 py-2 rounded-full text-sm font-semibold transition {{ $onglet === 'restitutions' ? 'bg-laiton text-white' : 'bg-white/5 text-argent/60 hover:bg-white/10' }}">
                ✅ Restitutions
            </a>
        </div>

        @if ($declarations->isEmpty())
            <div class="text-center text-argent/40 py-20">
                Aucune déclaration dans cette catégorie pour le moment.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($declarations as $declaration)
                    <div class="bg-white/5 border border-white/10 rounded-lg overflow-hidden">
                        @if ($declaration->piecesJointes->isNotEmpty())
                            <img src="{{ $declaration->piecesJointes->first()->url() }}"
                                 alt="{{ $declaration->categorie }}"
                                 class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-white/5 flex items-center justify-center text-4xl">
                                📋
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <x-status-badge :statut="$declaration->statut" />
                                @if ($declaration->localisation)
                                    <span class="text-xs text-argent/40">📍 {{ $declaration->localisation->adresse }}</span>
                                @endif
                            </div>
                            <h3 class="text-argent font-semibold text-sm mb-1">
                                {{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}
                            </h3>
                            <p class="text-xs text-argent/50 line-clamp-3">{{ $declaration->description }}</p>
                            <p class="text-xs text-argent/30 mt-2">{{ $declaration->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $declarations->links() }}
            </div>
        @endif

        <div class="mt-16 text-center bg-white/5 border border-white/10 rounded-lg p-8">
            <p class="text-argent/70 text-sm mb-4">
                Vous avez des informations sur l'un de ces cas, ou vous voulez signaler une disparition ?
            </p>
            <a href="{{ route('register') }}"
               class="inline-flex items-center px-6 py-3 bg-alerte rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                Créer un compte pour agir
            </a>
        </div>

    </main>

    <footer class="border-t border-white/10 py-6 text-center mt-10">
        <p class="text-xs text-argent/40">
            &copy; {{ date('Y') }} {{ config('app.name', 'Spotlight') }}. Tous droits réservés.
        </p>
    </footer>

</body>
</html>