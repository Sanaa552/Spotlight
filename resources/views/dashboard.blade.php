<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Fil d\'actualité') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-xl mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if ($declarations->isEmpty())
                <div class="bg-white shadow-sm border border-gray-100 rounded-xl p-10 text-center text-gray-500 flex flex-col items-center gap-2">
                    <x-icon name="megaphone" class="w-8 h-8 text-gray-300" />
                    Aucune déclaration publiée pour le moment.
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 items-start">
                    @foreach ($declarations as $declaration)
                        <article id="declaration-{{ $declaration->id }}" x-data="{ showComments: false }"
                                 class="bg-white shadow-sm border border-gray-100 rounded-xl overflow-hidden">

                            <div class="flex items-center gap-3 px-4 pt-4">
                                @if ($declaration->citoyen->photo_path)
                                    <img src="{{ $declaration->citoyen->photoUrl() }}" alt="{{ $declaration->citoyen->name }}"
                                         class="w-10 h-10 rounded-full object-cover shrink-0">
                                @else
                                    <span class="w-10 h-10 rounded-full bg-azur/15 text-azur text-sm font-bold flex items-center justify-center shrink-0">
                                        {{ $declaration->citoyen->initiales() }}
                                    </span>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $declaration->citoyen->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $declaration->created_at->diffForHumans() }}</p>
                                </div>
                                <x-type-badge :type="$declaration->type" />
                                <x-status-badge :statut="$declaration->statut" />
                            </div>

                            <div class="px-4 pt-3">
                                <h3 class="font-semibold text-gray-900">
                                    {{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}
                                </h3>
                                <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $declaration->description }}</p>
                                @if ($declaration->localisation)
                                    <p class="text-xs text-gray-400 mt-2 flex items-center gap-1.5">
                                        <x-icon name="location" class="w-3.5 h-3.5" />
                                        {{ $declaration->localisation->adresse }}
                                    </p>
                                @endif
                            </div>

                            @if ($declaration->piecesJointes->isNotEmpty())
                                <a href="{{ route('declarations.show', $declaration) }}" class="block mt-3">
                                    <img src="{{ $declaration->piecesJointes->first()->url() }}"
                                         alt="{{ $declaration->categorie }}"
                                         class="w-full h-56 object-cover">
                                </a>
                            @endif

                            <div class="px-4 py-3 flex items-center justify-between border-t border-gray-100 mt-2">
                                <button @click="showComments = ! showComments"
                                        class="flex items-center gap-1.5 text-sm font-medium text-alerte hover:text-alerte-dark transition">
                                    <x-icon name="megaphone" class="w-4 h-4" />
                                    J'ai une info
                                    @if ($declaration->commentaires->count() > 0)
                                        <span class="text-xs text-gray-400">({{ $declaration->commentaires->count() }})</span>
                                    @endif
                                </button>

                                <a href="{{ route('declarations.show', $declaration) }}" class="text-xs text-gray-400 hover:text-alerte transition">
                                    Voir le détail →
                                </a>
                            </div>

                            <div x-show="showComments" x-cloak x-transition class="border-t border-gray-100 bg-gray-50 px-4 py-4 space-y-4">

                                @forelse ($declaration->commentaires as $commentaire)
                                    <div class="flex items-start gap-2">
                                        @if ($commentaire->auteur->photo_path)
                                            <img src="{{ $commentaire->auteur->photoUrl() }}" alt="{{ $commentaire->auteur->name }}"
                                                 class="w-7 h-7 rounded-full object-cover shrink-0">
                                        @else
                                            <span class="w-7 h-7 rounded-full bg-laiton/15 text-laiton text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $commentaire->auteur->initiales() }}
                                            </span>
                                        @endif
                                        <div class="flex-1 bg-white rounded-lg px-3 py-2">
                                            <p class="text-xs font-semibold text-gray-800">{{ $commentaire->auteur->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $commentaire->contenu }}</p>
                                            <p class="text-[11px] text-gray-400 mt-1">{{ $commentaire->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 text-center">Aucune info partagée. Sois le premier à réagir !</p>
                                @endforelse

                                <form method="POST" action="{{ route('declarations.commenter', $declaration) }}" class="flex items-center gap-2 pt-2">
                                    @csrf
                                    <input type="text" name="contenu" required maxlength="1000"
                                           placeholder="Partager une info..."
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