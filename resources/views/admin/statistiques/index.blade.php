<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Statistiques') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Cartes chiffres clés --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ array_sum($repartitionStatuts) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total déclarations</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-ambre">{{ $repartitionStatuts['en_attente'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">En attente</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-sonar">{{ $repartitionStatuts['validee'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Validées</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-laiton">{{ $repartitionStatuts['cloturee'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Restituées</p>
                </div>
            </div>

            {{-- Graphiques --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="bg-white shadow-sm rounded-lg p-4 lg:col-span-2">
                    <h3 class="font-semibold text-gray-900 text-sm mb-3">Évolution sur 6 mois</h3>
                    <canvas id="chartEvolution" height="220"></canvas>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 text-sm mb-3">Répartition par statut</h3>
                    <canvas id="chartStatuts" height="220"></canvas>
                </div>

            </div>

            <div class="bg-white shadow-sm rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 text-sm mb-3">Restitutions confirmées par mois</h3>
                <canvas id="chartRestitutions" height="100"></canvas>
            </div>

            {{-- Générer une nouvelle statistique (système existant) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Générer une statistique ponctuelle</h3>
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
                    Aucune statistique ponctuelle générée pour le moment.
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const labels = @json($chartLabels);

        // Graphique 1 : évolution pertes vs découvertes (lignes)
        new Chart(document.getElementById('chartEvolution'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Pertes',
                        data: @json($chartPertes),
                        borderColor: '#E31E24',
                        backgroundColor: 'rgba(227, 30, 36, 0.1)',
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Découvertes',
                        data: @json($chartDecouvertes),
                        borderColor: '#12877F',
                        backgroundColor: 'rgba(18, 135, 127, 0.1)',
                        tension: 0.3,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            },
        });

        // Graphique 2 : répartition par statut (donut)
        new Chart(document.getElementById('chartStatuts'), {
            type: 'doughnut',
            data: {
                labels: ['En attente', 'Validées', 'Rejetées', 'Clôturées'],
                datasets: [{
                    data: [
                        {{ $repartitionStatuts['en_attente'] }},
                        {{ $repartitionStatuts['validee'] }},
                        {{ $repartitionStatuts['rejetee'] }},
                        {{ $repartitionStatuts['cloturee'] }},
                    ],
                    backgroundColor: ['#FDC105', '#12877F', '#E31E24', '#C99A2E'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
            },
        });

        // Graphique 3 : restitutions confirmées par mois (barres)
        new Chart(document.getElementById('chartRestitutions'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Restitutions confirmées',
                    data: @json($chartRestitutions),
                    backgroundColor: '#12579B',
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            },
        });
    </script>
</x-app-layout>