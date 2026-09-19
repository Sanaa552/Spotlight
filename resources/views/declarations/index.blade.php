<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                {{ __('Mes déclarations') }}
            </h2>
            <a href="{{ route('declarations.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-alerte border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark focus:outline-none focus:ring-2 focus:ring-alerte focus:ring-offset-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle déclaration
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-xl">
                    {{ session('success') }}
                </div>
            @endif

            @if ($declarations->isEmpty())
                <div class="bg-white shadow-sm border border-gray-100 rounded-xl p-10 text-center text-gray-500 flex flex-col items-center gap-3">
                    <x-icon name="search" class="w-8 h-8 text-gray-300" />
                    <p>Aucune déclaration pour le moment.</p>
                    <a href="{{ route('declarations.create') }}" class="text-alerte font-medium hover:underline">
                        Créer votre première déclaration
                    </a>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($declarations as $declaration)
                        <a href="{{ route('declarations.show', $declaration) }}"
                           class="block bg-white shadow-sm border border-gray-100 rounded-md p-4 sm:p-5 hover:shadow-md transition border-l-4 {{ $declaration->type === 'perte' ? 'border-l-alerte' : 'border-l-sonar' }}">
                            <div class="flex items-start gap-3 sm:gap-4">
                                @if ($declaration->photoUrl())
                                    <img src="{{ $declaration->photoUrl() }}"
                                         alt="Photo de {{ $declaration->type_perte ?? $declaration->type_decouverte ?? $declaration->categorie }}"
                                         loading="lazy"
                                         class="w-20 h-20 sm:w-28 sm:h-28 shrink-0 rounded-md object-cover bg-gray-100">
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <x-type-badge :type="$declaration->type" />
                                        <x-status-badge :statut="$declaration->statut" />
                                        <span class="text-xs text-gray-400">#{{ $declaration->id }}</span>
                                    </div>
                                    @if (in_array($declaration->publication_status, ['queued', 'processing'], true))
                                        <p class="text-xs font-medium text-azur">Publication en cours de traitement</p>
                                    @elseif ($declaration->publication_status === 'failed')
                                        <p class="text-xs font-medium text-laiton">Publication non aboutie ; l'équipe de modération peut la relancer.</p>
                                    @endif
                                    <h3 class="font-semibold text-gray-900 break-words">{{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}</h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $declaration->description }}</p>
                                    @if ($declaration->localisation)
                                        <p class="text-xs text-gray-400 mt-2 flex items-center gap-1.5">
                                            <x-icon name="location" class="w-3.5 h-3.5" />
                                            {{ $declaration->localisation->adresse }}
                                        </p>
                                    @endif
                                    <span class="block text-xs text-gray-400 mt-2">{{ $declaration->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $declarations->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
