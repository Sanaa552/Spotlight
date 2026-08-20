<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Statistiques') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Générer une nouvelle statistique --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Générer une nouvelle statistique</h3>
                <form method="POST" action="{{ route('admin.statistiques.generer') }}" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div class="flex-1 min-w-[240px]">
                        <x-input-label for="type" value="Type de statistique" />
                        <select id="type" name="type" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte">
                            <option value="declarations_par_statut">Déclarations par statut</option>
                            <option value="declarations_par_type">Déclarations par type (perte/découverte)</option>
                            <option value="taux_restitution">Taux de restitution</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                        Générer
                    </button>
                </form>
            </div>

            {{-- Historique --}}
            @if ($statistiques->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    Aucune statistique générée pour le moment.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($statistiques as $stat)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-semibold text-gray-900">
                                    {{ str($stat->type)->replace('_', ' ')->ucfirst() }}
                                </h4>
                                <span class="text-xs text-gray-400">
                                    {{ $stat->date_generation->format('d/m/Y à H:i') }}
                                    par {{ $stat->administrateur->name }}
                                </span>
                            </div>

                            @if ($stat->type === 'taux_restitution' && is_array($stat->donnees))
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-2xl font-bold text-gray-900">{{ $stat->donnees['total'] ?? 0 }}</p>
                                        <p class="text-xs text-gray-500 mt-1">Total</p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <p class="text-2xl font-bold text-gray-900">{{ $stat->donnees['cloturees'] ?? 0 }}</p>
                                        <p class="text-xs text-gray-500 mt-1">Clôturées</p>
                                    </div>
                                    <div class="bg-sonar/10 rounded-lg p-4">
                                        <p class="text-2xl font-bold text-sonar-dark">{{ $stat->donnees['taux'] ?? 0 }}%</p>
                                        <p class="text-xs text-gray-500 mt-1">Taux de restitution</p>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach (($stat->donnees ?? []) as $cle => $valeur)
                                        <div class="bg-gray-50 rounded-lg px-4 py-2">
                                            <span class="text-xs text-gray-500">{{ ucfirst($cle) }}</span>
                                            <p class="text-lg font-bold text-gray-900">{{ $valeur }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $statistiques->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>