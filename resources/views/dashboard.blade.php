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
            @if ($errors->any())
                <div class="mb-6 rounded-md border border-alerte/30 bg-alerte/10 px-4 py-3 text-sm text-alerte-dark" role="alert">
                    {{ $errors->first() }}
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
                        @php
                            $detailRoute = auth()->user()->isCitoyen() && $declaration->user_id === auth()->id()
                                ? route('declarations.show', $declaration)
                                : (auth()->user()->isCitoyen()
                                    ? route('public.declarations.show', $declaration)
                                    : route('moderation.declarations.show', $declaration));
                        @endphp
                        <article id="declaration-{{ $declaration->id }}" x-data="{ showComments: window.location.hash === '#declaration-{{ $declaration->id }}', replyTo: null, replyName: '' }"
                                 class="bg-white shadow-sm border border-gray-100 rounded-xl overflow-hidden">

                            <div class="flex items-center gap-3 px-4 pt-4">
                                @if ($declaration->citoyen->photoUrl())
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
                                @if ($declaration->lieu)
                                    <p class="text-xs text-gray-400 mt-2 flex items-center gap-1.5">
                                        <x-icon name="location" class="w-3.5 h-3.5" />
                                        {{ $declaration->lieu }}
                                    </p>
                                @endif
                            </div>

                            @if ($declaration->photoUrl())
                                <a href="{{ $detailRoute }}" class="mt-3 block overflow-hidden">
                                    <img src="{{ $declaration->photoUrl() }}"
                                         alt="Photo de {{ $declaration->type_perte ?? $declaration->type_decouverte ?? $declaration->categorie }}"
                                         class="h-56 w-full object-cover">
                                </a>
                            @endif

                            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 mt-2">
                                <div class="flex flex-wrap gap-3 text-xs font-medium text-azur">
                                    @if ($declaration->facebook_post_url)
                                        <a href="{{ $declaration->facebook_post_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Facebook</a>
                                    @endif
                                    @if ($declaration->instagram_post_url)
                                        <a href="{{ $declaration->instagram_post_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Instagram</a>
                                    @endif
                                </div>
                                <button @click="showComments = ! showComments"
                                        class="flex items-center gap-1.5 text-sm font-medium text-alerte hover:text-alerte-dark transition">
                                    <x-icon name="megaphone" class="w-4 h-4" />
                                    J'ai une info
                                    @if ($declaration->commentaires->count() > 0)
                                        <span class="text-xs text-gray-400">({{ $declaration->commentaires->count() }})</span>
                                    @endif
                                </button>

                                <a href="{{ $detailRoute }}" class="text-xs text-gray-400 hover:text-alerte transition">
                                    Voir le détail
                                </a>
                            </div>

                            <div x-show="showComments" x-cloak x-transition class="border-t border-gray-100 bg-gray-50 px-4 py-4 space-y-4">

                                @forelse ($declaration->commentaires as $commentaire)
                                    <div class="flex items-start gap-2">
                                        @if ($commentaire->auteur->photoUrl())
                                            <img src="{{ $commentaire->auteur->photoUrl() }}" alt="{{ $commentaire->auteur->name }}"
                                                 class="w-7 h-7 rounded-full object-cover shrink-0">
                                        @else
                                            <span class="w-7 h-7 rounded-full bg-laiton/15 text-laiton text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $commentaire->auteur->initiales() }}
                                            </span>
                                        @endif
                                        <div class="flex-1 min-w-0 bg-white rounded-lg px-3 py-2">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-xs font-semibold text-gray-800">{{ $commentaire->auteur->name }}</p>
                                                @if (auth()->user()->isModerateur() || auth()->user()->isAdministrateur())
                                                    <button type="button" title="Supprimer ce commentaire"
                                                            aria-label="Supprimer le commentaire de {{ $commentaire->auteur->name }}"
                                                            x-on:click="$dispatch('open-modal', 'delete-comment-{{ $commentaire->id }}')"
                                                            class="shrink-0 p-1 text-gray-400 hover:text-alerte">
                                                        <x-icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                @endif
                                            </div>
                                            @if ($commentaire->parent)
                                                <p class="mt-1 text-xs text-azur">En réponse à {{ $commentaire->parent->auteur?->name ?? 'un membre' }}</p>
                                            @endif
                                            <p class="text-sm text-gray-600">{{ $commentaire->contenu }}</p>
                                            <div class="mt-1 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-gray-400">{{ $commentaire->created_at->diffForHumans() }}</p>
                                                <button type="button" data-author="{{ $commentaire->auteur->name }}"
                                                        x-on:click="replyTo = {{ $commentaire->id }}; replyName = $el.dataset.author; $nextTick(() => $refs.commentInput.focus())"
                                                        class="text-xs font-medium text-azur hover:underline">Répondre</button>
                                            </div>
                                        </div>
                                    </div>
                                    @if (auth()->user()->isModerateur() || auth()->user()->isAdministrateur())
                                        <x-modal name="delete-comment-{{ $commentaire->id }}" maxWidth="md" focusable>
                                            <div class="p-6">
                                                <h3 class="text-base font-semibold text-gray-900">Supprimer ce commentaire ?</h3>
                                                <p class="mt-2 text-sm text-gray-600">Cette action retirera définitivement l'information du fil.</p>
                                                <div class="mt-5 flex justify-end gap-3">
                                                    <button type="button" x-on:click="$dispatch('close-modal', 'delete-comment-{{ $commentaire->id }}')"
                                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Annuler</button>
                                                    <form method="POST" action="{{ route('moderation.commentaires.supprimer', $commentaire) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-4 py-2 rounded-md bg-alerte text-sm font-semibold text-white hover:bg-alerte-dark">Supprimer</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </x-modal>
                                    @endif
                                @empty
                                    <p class="text-xs text-gray-400 text-center">Aucune info partagée. Sois le premier à réagir !</p>
                                @endforelse

                                <div x-show="replyTo" x-cloak class="flex items-center justify-between gap-2 text-xs text-azur">
                                    <span>Réponse à <span x-text="replyName"></span></span>
                                    <button type="button" x-on:click="replyTo = null; replyName = ''" class="text-gray-500 hover:text-gray-900" aria-label="Annuler la réponse">Annuler</button>
                                </div>
                                <form method="POST" action="{{ route('declarations.commenter', $declaration) }}" class="flex items-center gap-2 pt-2">
                                    @csrf
                                    <input type="hidden" name="parent_id" x-bind:value="replyTo || ''">
                                    <input type="text" name="contenu" required maxlength="1000"
                                           x-ref="commentInput"
                                           placeholder="Partager une info..."
                                           class="flex-1 text-sm border-gray-300 rounded-full focus:border-alerte focus:ring-alerte">
                                    <button type="submit" title="Envoyer cette information" aria-label="Envoyer cette information"
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
