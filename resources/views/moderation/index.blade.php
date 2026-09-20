<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Modération — Déclarations en attente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex flex-wrap gap-4 border-b border-white/15 pb-3 text-sm font-medium">
                <span class="border-b-2 border-alerte pb-2 text-white">En attente</span>
                <a href="{{ route('moderation.published') }}" class="pb-2 text-argent/70 hover:text-white">Publiées et rappels</a>
            </div>

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="bg-laiton/10 border border-laiton/30 text-laiton px-4 py-3 rounded-lg">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($declarations->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    Aucune déclaration en attente. 
                </div>
            @else
                @foreach ($declarations as $declaration)
                    <div x-data="publicationTracker('{{ route('declarations.publication-status', $declaration) }}', '{{ $declaration->publication_status }}')"
                         data-publication-error="{{ $declaration->publication_error }}"
                         class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 {{ $declaration->type === 'perte' ? 'border-alerte' : 'border-sonar' }}">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-type-badge :type="$declaration->type" />
                                    <x-status-badge :statut="$declaration->statut" />
                                    <span class="text-xs text-gray-400">#{{ $declaration->id }}</span>
                                    @if ($declaration->facebook_post_id && ! $declaration->instagram_post_id)
                                        <span class="text-xs font-medium text-azur">Publication partielle : Facebook en ligne, Instagram à reprendre</span>
                                    @endif
                                </div>
                                <p x-show="status === 'queued'" style="display:none" class="mb-2 text-sm text-azur" role="status">Publication en attente de traitement…</p>
                                <p x-show="status === 'processing'" style="display:none" class="mb-2 flex items-center gap-2 text-sm text-azur" role="status">
                                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-azur border-t-transparent" aria-hidden="true"></span>
                                    Publication sur Facebook et Instagram en cours…
                                </p>
                                <p x-show="delayed" style="display:none" class="mb-2 text-sm text-laiton" role="alert">Le traitement prend plus de temps que prévu. Vérifiez que le worker de la file fonctionne.</p>
                                <p x-show="status === 'failed'" style="display:none" class="mb-2 text-sm text-alerte-dark" role="alert">
                                    <span x-text="error || 'Publication non confirmée.'"></span> Le dossier reste en attente ; vous pouvez relancer après vérification.
                                </p>
                                <p x-show="status === 'succeeded'" style="display:none" class="mb-2 text-sm text-sonar-dark" role="status">Publication Facebook et Instagram confirmée. Déclaration validée.</p>

                                <h3 class="font-semibold text-gray-900">{{ $declaration->categorie }}</h3>
                                <p class="text-sm text-gray-600 mt-1">{{ $declaration->description }}</p>

                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                    <span> {{ $declaration->citoyen->name }}</span>
                                    @if ($declaration->localisation)
                                        <span> {{ $declaration->localisation->adresse }}</span>
                                    @endif
                                    <span> {{ $declaration->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t flex flex-wrap gap-3">
                            <button type="button" x-show="status !== 'failed'"
                                    x-bind:disabled="status === 'queued' || status === 'processing'"
                                    x-on:click="$dispatch('open-modal', 'validate-declaration-{{ $declaration->id }}')"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-sonar border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-sonar-dark disabled:opacity-50 disabled:cursor-not-allowed transition">
                                <x-icon name="check-circle" class="h-4 w-4" />
                                <span>Valider</span>
                            </button>
                            <form method="POST" action="{{ route('moderation.valider', $declaration) }}"
                                  x-show="status === 'failed'" x-cloak x-data="{ submitting: false }"
                                  x-on:submit="submitting = true">
                                @csrf
                                <button type="submit" x-bind:disabled="submitting"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-sonar border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-sonar-dark disabled:opacity-50 disabled:cursor-wait transition">
                                    <span x-show="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" aria-hidden="true"></span>
                                    <x-icon name="refresh-cw" class="h-4 w-4" x-show="!submitting" />
                                    {{ $declaration->facebook_post_id ? 'Reprendre Instagram' : 'Réessayer la publication' }}
                                </button>
                            </form>

                            <button type="button"
                                    x-bind:disabled="status === 'queued' || status === 'processing'"
                                    x-on:click="$dispatch('open-modal', 'reject-declaration-{{ $declaration->id }}')"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark disabled:opacity-50 disabled:cursor-not-allowed transition">
                                <x-icon name="x-circle" class="h-4 w-4" />
                                Rejeter
                            </button>

                            <a href="{{ route('moderation.declarations.show', $declaration) }}"
                               class="inline-flex items-center px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800 uppercase tracking-widest">
                                Voir le détail
                            </a>
                        </div>

                        <x-modal name="validate-declaration-{{ $declaration->id }}" maxWidth="md" focusable>
                            <form method="POST" action="{{ route('moderation.valider', $declaration) }}"
                                  x-data="{ submitting: false }" x-on:submit="submitting = true" class="p-6">
                                @csrf
                                <h2 class="text-lg font-semibold text-gray-900">Valider cette déclaration ?</h2>
                                <p class="mt-2 text-sm leading-6 text-gray-600">
                                    @if ($declaration->type === 'decouverte' && $declaration->categorie === 'personne')
                                        Le signalement <strong>#{{ $declaration->id }}</strong> sera vérifié en interne. Il ne sera publié ni sur Spotlight ni sur les réseaux sociaux. Vérifiez la preuve de signalement aux autorités.
                                    @else
                                        La déclaration <strong>#{{ $declaration->id }}</strong> sera rendue publique seulement après vérification des preuves privées et confirmation des publications Facebook et Instagram.
                                    @endif
                                    @if ($declaration->facebook_post_id)
                                        Facebook est déjà publié et ne sera pas republié.
                                    @endif
                                </p>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                    <button type="submit" x-bind:disabled="submitting"
                                            class="inline-flex items-center justify-center gap-2 rounded-md bg-sonar px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-sonar-dark disabled:opacity-60 disabled:cursor-wait">
                                        <span x-show="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" aria-hidden="true"></span>
                                        <span x-show="!submitting">{{ $declaration->type === 'decouverte' && $declaration->categorie === 'personne' ? 'Confirmer en privé' : 'Valider et publier' }}</span>
                                        <span x-show="submitting">Envoi en cours…</span>
                                    </button>
                                </div>
                            </form>
                        </x-modal>

                        <x-modal name="reject-declaration-{{ $declaration->id }}" maxWidth="md" focusable>
                            <form method="POST" action="{{ route('moderation.rejeter', $declaration) }}" class="p-6">
                                @csrf
                                <h2 class="text-lg font-semibold text-gray-900">Rejeter cette déclaration ?</h2>
                                <p class="mt-2 text-sm leading-6 text-gray-600">
                                    Le citoyen recevra le motif du rejet de la déclaration <strong>#{{ $declaration->id }}</strong>.
                                </p>
                                <label for="motif-rejet-{{ $declaration->id }}" class="mt-4 block text-sm font-medium text-gray-700">Motif du rejet</label>
                                <textarea name="motif_rejet" rows="2" required
                                          id="motif-rejet-{{ $declaration->id }}"
                                          class="block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte"
                                          placeholder="Expliquez pourquoi cette déclaration est rejetée..."></textarea>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                    <button type="submit"
                                            class="inline-flex justify-center px-4 py-2 bg-alerte text-white text-xs font-semibold uppercase rounded-md hover:bg-alerte-dark">
                                        Confirmer le rejet
                                    </button>
                                </div>
                            </form>
                        </x-modal>
                    </div>
                @endforeach

                <div class="mt-4">
                    {{ $declarations->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
