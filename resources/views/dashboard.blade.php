<x-app-layout>
        <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Fil d\'actualité') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-0">



            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Fil des déclarations --}}
            @if ($declarations->isEmpty())
                <div class="bg-white shadow-sm rounded-lg p-10 text-center text-gray-500">
                    Aucune déclaration publiée pour le moment.
                </div>
            @else
                <div class="space-y-6">
                    @foreach ($declarations as $declaration)
                        <article id="declaration-{{ $declaration->id }}" x-data="{ showComments: false }"
                                 class="bg-white shadow-sm rounded-lg overflow-hidden">

                            {{-- En-tête : auteur --}}
                            <div class="flex items-center gap-3 px-4 pt-4">
                                <span class="w-10 h-10 rounded-full bg-azur/15 text-azur text-sm font-bold flex items-center justify-center shrink-0">
                                    {{ collect(explode(' ', $declaration->citoyen->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $declaration->citoyen->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $declaration->created_at->diffForHumans() }}</p>
                                </div>
                                <x-type-badge :type="$declaration->type" />
                                <x-status-badge :statut="$declaration->statut" />
                            </div>

                            {{-- Corps --}}
                            <div class="px-4 pt-3">
                                <h3 class="font-semibold text-gray-900">
                                    {{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}
                                </h3>
                                <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $declaration->description }}</p>
                                @if ($declaration->localisation)
                                    <p class="text-xs text-gray-400 mt-2">📍 {{ $declaration->localisation->adresse }}</p>
                                @endif
                            </div>

                            {{-- Photo --}}
                            @if ($declaration->piecesJointes->isNotEmpty())
                                <a href="{{ route('declarations.show', $declaration) }}" class="block mt-3">
                                    <img src="{{ $declaration->piecesJointes->first()->url() }}"
                                         alt="{{ $declaration->categorie }}"
                                         class="w-full max-h-96 object-cover">
                                </a>
                            @endif

                            {{-- Barre d'actions --}}
                            <div class="px-4 py-3 flex items-center justify-between border-t border-gray-100 mt-2">
                                <button @click="showComments = ! showComments"
                                        class="flex items-center gap-2 text-sm text-gray-500 hover:text-alerte transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8-1.06 0-2.077-.163-3.02-.465L3 21l1.395-3.72C3.512 16.032 3 14.574 3 13c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    {{ $declaration->commentaires->count() }}
                                    {{ $declaration->commentaires->count() > 1 ? 'commentaires' : 'commentaire' }}
                                </button>

                                <a href="{{ route('declarations.show', $declaration) }}" class="text-xs text-gray-400 hover:text-alerte transition">
                                    Voir le détail →
                                </a>
                            </div>

                            {{-- Commentaires --}}
                            <div x-show="showComments" x-cloak x-transition class="border-t border-gray-100 bg-gray-50 px-4 py-4 space-y-4">

                                @forelse ($declaration->commentaires as $commentaire)
                                    <div class="flex items-start gap-2">
                                        <span class="w-7 h-7 rounded-full bg-laiton/15 text-laiton text-xs font-bold flex items-center justify-center shrink-0">
                                            {{ collect(explode(' ', $commentaire->auteur->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                        </span>
                                        <div class="flex-1 bg-white rounded-lg px-3 py-2">
                                            <p class="text-xs font-semibold text-gray-800">{{ $commentaire->auteur->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $commentaire->contenu }}</p>
                                            <p class="text-[11px] text-gray-400 mt-1">{{ $commentaire->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 text-center">Aucun commentaire. Sois le premier à réagir !</p>
                                @endforelse

                                {{-- Nouveau commentaire --}}
                                <form method="POST" action="{{ route('declarations.commenter', $declaration) }}" class="flex items-center gap-2 pt-2">
                                    @csrf
                                    <input type="text" name="contenu" required maxlength="1000"
                                           placeholder="Écrire un commentaire..."
                                           class="flex-1 text-sm border-gray-300 rounded-full focus:border-alerte focus:ring-alerte">
                                    <button type="submit"
                                            class="shrink-0 w-9 h-9 rounded-full bg-alerte text-white flex items-center justify-center hover:bg-alerte-dark transition">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $declarations->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>