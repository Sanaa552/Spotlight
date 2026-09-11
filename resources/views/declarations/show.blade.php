<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                {{ __('Déclaration') }} #{{ $declaration->id }}
            </h2>
            <a href="{{ route('declarations.index') }}" class="text-sm text-alerte hover:underline">
                ← Retour à mes déclarations
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-xl">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('proposer_carte') && $declaration->localisation)
                <div class="bg-azur/10 border border-azur/30 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-azur">
                        Ta déclaration est enregistrée. Trouve le commissariat le plus proche pour y déposer l'objet ou signaler la personne.
                    </p>
                    <a href="{{ route('declarations.commissariats', $declaration) }}"
                       class="shrink-0 inline-flex items-center gap-2 justify-center px-5 py-2 bg-azur text-white text-xs font-semibold uppercase tracking-widest rounded-lg hover:bg-azur-dark transition">
                        <x-icon name="car" class="w-4 h-4" />
                        Accéder à la carte
                    </a>
                </div>
            @endif

            {{-- En-tête statut --}}
            <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6 border-l-4 {{ $declaration->type === 'perte' ? 'border-l-alerte' : 'border-l-sonar' }}">
                <div class="flex items-center gap-2 mb-4">
                    <x-type-badge :type="$declaration->type" />
                    <x-status-badge :statut="$declaration->statut" />
                    <span class="text-xs text-gray-400">
                        Créée {{ $declaration->created_at->diffForHumans() }}
                    </span>
                </div>

                <h3 class="text-lg font-semibold text-gray-900">{{ $declaration->categorie }}</h3>
                @if ($declaration->type === 'perte' && $declaration->type_perte)
                    <p class="text-sm text-gray-500">{{ $declaration->type_perte }}</p>
                @elseif ($declaration->type === 'decouverte' && $declaration->type_decouverte)
                    <p class="text-sm text-gray-500">{{ $declaration->type_decouverte }}</p>
                @endif

                <p class="mt-4 text-gray-700 whitespace-pre-line">{{ $declaration->description }}</p>

                @if ($declaration->lieu)
                    <p class="mt-4 flex items-center gap-1.5 text-sm text-gray-500">
                        <x-icon name="location" class="w-4 h-4 text-gray-400" />
                        {{ $declaration->lieu }}
                    </p>
                @endif

                @if ($declaration->statut === 'rejetee' && $declaration->motif_rejet)
                    <div class="mt-4 bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg text-sm flex items-start gap-2">
                        <x-icon name="x-circle" class="w-4 h-4 shrink-0 mt-0.5" />
                        <span><strong>Motif du rejet :</strong> {{ $declaration->motif_rejet }}</span>
                    </div>
                @endif
            </div>

            {{-- Localisation --}}
            @if ($declaration->localisation)
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <x-icon name="location" class="w-4 h-4 text-azur" />
                        Localisation
                    </h4>
                    <p class="text-sm text-gray-700">{{ $declaration->localisation->adresse }}</p>
                    @if ($declaration->localisation->latitude && $declaration->localisation->longitude)
                        <p class="text-xs text-gray-400 mt-1">
                            {{ $declaration->localisation->latitude }}, {{ $declaration->localisation->longitude }}
                        </p>
                    @endif

                    @if ($declaration->type === 'decouverte')
                        <a href="{{ route('declarations.commissariats', $declaration) }}"
                           class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-azur text-white text-xs font-semibold uppercase tracking-widest rounded-lg hover:bg-azur-dark transition">
                            <x-icon name="car" class="w-4 h-4" />
                            Voir les commissariats proches
                        </a>
                    @endif
                </div>
            @endif

            {{-- Pièces jointes --}}
            @if ($declaration->piecesJointes->isNotEmpty())
                <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <x-icon name="paperclip" class="w-4 h-4 text-azur" />
                        Pièces jointes
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($declaration->piecesJointes as $piece)
                            <a href="{{ $piece->url() }}" target="_blank"
                               class="block border border-gray-100 rounded-lg overflow-hidden hover:opacity-80 transition">
                                @if ($piece->estImage())
                                    <img src="{{ $piece->url() }}" alt="{{ $piece->nom_original }}"
                                         class="w-full h-28 object-cover">
                                @else
                                    <div class="w-full h-28 flex items-center justify-center bg-gray-50 text-gray-300">
                                        <x-icon name="paperclip" class="w-8 h-8" />
                                    </div>
                                @endif
                                <p class="text-xs text-gray-500 truncate px-2 py-1">{{ $piece->nom_original }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Suivi / historique --}}
            <div class="bg-white shadow-sm border border-gray-100 overflow-hidden rounded-xl p-6">
                <h4 class="font-semibold text-gray-900 mb-3">Suivi</h4>
                <ul class="text-sm text-gray-600 space-y-2.5">
                    <li class="flex items-center gap-2">
                        <x-icon name="clock" class="w-4 h-4 text-gray-400" />
                        Déclaration soumise le {{ $declaration->created_at->format('d/m/Y à H:i') }}
                    </li>
                    @if ($declaration->moderateur)
                        <li class="flex items-center gap-2">
                            <x-icon name="user" class="w-4 h-4 text-gray-400" />
                            Traitée par {{ $declaration->moderateur->name }}
                        </li>
                    @endif
                    @if ($declaration->statut === 'cloturee' && $declaration->cloturee_at)
                        <li class="flex items-center gap-2">
                            <x-icon name="check-circle" class="w-4 h-4 text-sonar" />
                            Clôturée le {{ $declaration->cloturee_at->format('d/m/Y à H:i') }}
                        </li>
                    @endif
                </ul>
            </div>

            {{-- Action : confirmer restitution --}}
            @if ($declaration->statut === 'validee')
                <form method="POST" action="{{ route('declarations.confirmer-restitution', $declaration) }}">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Confirmer que l\'objet/personne a bien été restitué(e) ?')"
                            class="w-full inline-flex justify-center items-center gap-2 px-4 py-3 bg-sonar border border-transparent rounded-lg font-semibold text-sm text-white uppercase tracking-widest hover:bg-sonar-dark focus:outline-none focus:ring-2 focus:ring-sonar focus:ring-offset-2 transition">
                        <x-icon name="check-circle" class="w-5 h-5" />
                        Confirmer la restitution
                    </button>
                </form>
            @endif

        </div>
    </div>
</x-app-layout>