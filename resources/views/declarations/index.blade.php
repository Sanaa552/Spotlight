<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                {{ __('Mes déclarations') }}
            </h2>
            <a href="{{ route('declarations.create') }}"
               class="inline-flex items-center px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark focus:outline-none focus:ring-2 focus:ring-alerte focus:ring-offset-2 transition">
                + Nouvelle déclaration
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($declarations->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    Aucune déclaration pour le moment.
                    <a href="{{ route('declarations.create') }}" class="text-alerte font-medium hover:underline">
                        Créer votre première déclaration
                    </a>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($declarations as $declaration)
                        <a href="{{ route('declarations.show', $declaration) }}"
                           class="block bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition border-l-4 {{ $declaration->type === 'perte' ? 'border-alerte' : 'border-sonar' }}">
                            <div class="flex justify-between items-start gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <x-type-badge :type="$declaration->type" />
                                        <x-status-badge :statut="$declaration->statut" />
                                        <span class="text-xs text-gray-400">#{{ $declaration->id }}</span>
                                    </div>
                                    <h3 class="font-semibold text-gray-900">{{ $declaration->categorie }}</h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $declaration->description }}</p>
                                    @if ($declaration->localisation)
                                        <p class="text-xs text-gray-400 mt-2">
                                            📍 {{ $declaration->localisation->adresse }}
                                        </p>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-400 whitespace-nowrap">
                                    {{ $declaration->created_at->diffForHumans() }}
                                </span>
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