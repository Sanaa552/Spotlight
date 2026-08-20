<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Modération — Déclarations en attente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($declarations->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    Aucune déclaration en attente. 🎉
                </div>
            @else
                @foreach ($declarations as $declaration)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 {{ $declaration->type === 'perte' ? 'border-alerte' : 'border-sonar' }}">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-type-badge :type="$declaration->type" />
                                    <x-status-badge :statut="$declaration->statut" />
                                    <span class="text-xs text-gray-400">#{{ $declaration->id }}</span>
                                </div>

                                <h3 class="font-semibold text-gray-900">{{ $declaration->categorie }}</h3>
                                <p class="text-sm text-gray-600 mt-1">{{ $declaration->description }}</p>

                                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                    <span>👤 {{ $declaration->citoyen->name }}</span>
                                    @if ($declaration->localisation)
                                        <span>📍 {{ $declaration->localisation->adresse }}</span>
                                    @endif
                                    <span>🕒 {{ $declaration->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('moderation.valider', $declaration) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-sonar border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-sonar-dark transition">
                                    ✅ Valider
                                </button>
                            </form>

                            <button type="button"
                                    onclick="document.getElementById('rejet-modal-{{ $declaration->id }}').classList.remove('hidden')"
                                    class="inline-flex items-center px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                                ❌ Rejeter
                            </button>

                            <a href="{{ route('declarations.show', $declaration) }}"
                               class="inline-flex items-center px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800 uppercase tracking-widest">
                                Voir le détail
                            </a>
                        </div>

                        {{-- Modal de rejet (simple, sans JS framework) --}}
                        <div id="rejet-modal-{{ $declaration->id }}" class="hidden mt-4 bg-alerte/5 border border-alerte/20 rounded-lg p-4">
                            <form method="POST" action="{{ route('moderation.rejeter', $declaration) }}">
                                @csrf
                                <label class="block text-sm font-medium text-gray-700 mb-2">Motif du rejet</label>
                                <textarea name="motif_rejet" rows="2" required
                                          class="block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte"
                                          placeholder="Expliquez pourquoi cette déclaration est rejetée..."></textarea>
                                <div class="mt-3 flex gap-2">
                                    <button type="submit"
                                            class="px-4 py-2 bg-alerte text-white text-xs font-semibold uppercase rounded-md hover:bg-alerte-dark">
                                        Confirmer le rejet
                                    </button>
                                    <button type="button"
                                            onclick="document.getElementById('rejet-modal-{{ $declaration->id }}').classList.add('hidden')"
                                            class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">
                                        Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach

                <div class="mt-4">
                    {{ $declarations->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>