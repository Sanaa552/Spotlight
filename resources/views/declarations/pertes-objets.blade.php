<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-argent">Objets déclarés perdus</h2>
            <a href="{{ $decouverte ? route('declarations.show', $decouverte) : route('declarations.create') }}" class="text-sm text-azur hover:underline">Retour</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <form method="GET" action="{{ route('rapprochements.pertes') }}" class="flex gap-2">
            @if ($decouverte)<input type="hidden" name="decouverte" value="{{ $decouverte->id }}">@endif
            <label for="search-loss" class="sr-only">Chercher un objet perdu</label>
            <input id="search-loss" name="q" value="{{ $q }}" maxlength="80" placeholder="Objet, description ou quartier"
                   class="min-w-0 flex-1 rounded-md border-gray-300 text-sm focus:border-azur focus:ring-azur">
            <button type="submit" class="rounded-md bg-azur px-4 py-2 text-sm font-semibold text-white">Chercher</button>
        </form>
        <p class="mt-3 text-sm text-argent/70">Une ressemblance ne prouve pas la propriété. La modération doit vérifier les dossiers avant de contacter les déclarants.</p>

        @if (session('warning'))
            <p class="mt-4 border-l-2 border-laiton pl-3 text-sm text-argent" role="status">{{ session('warning') }}</p>
        @endif
        <p id="match-selection-status" role="status" aria-live="polite" class="mt-4 hidden border-l-2 border-sonar pl-3 text-sm text-argent"></p>

        @if ($pertes->isEmpty())
            <p class="py-12 text-center text-sm text-argent/70">Aucune perte correspondante. Vous pouvez déclarer votre découverte sans annonce liée.</p>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pertes as $perte)
                    <article class="overflow-hidden rounded-md border border-white/10 bg-white text-gray-900">
                        <img src="{{ $perte->photoUrl() }}" alt="Photo publique de {{ $perte->type_perte ?: 'cet objet' }}" loading="lazy" class="h-40 w-full object-cover bg-gray-100">
                        <div class="p-4">
                            <h3 class="text-sm font-semibold">{{ $perte->type_perte ?: 'Objet perdu' }} <span class="font-normal text-gray-500">#{{ $perte->id }}</span></h3>
                            @if ($perte->lieu)<p class="mt-1 text-xs text-gray-500">{{ $perte->lieu }}</p>@endif
                            <p class="mt-2 line-clamp-3 text-sm text-gray-700">{{ $perte->description }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
                                <a href="{{ route('public.declarations.show', $perte) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-azur hover:underline">Voir l’annonce</a>
                                @if ($decouverte)
                                    <form method="POST" action="{{ route('rapprochements.proposer', $decouverte) }}">
                                        @csrf
                                        <input type="hidden" name="perte_id" value="{{ $perte->id }}">
                                        <button type="submit" class="font-semibold text-sonar-dark hover:underline">Proposer la correspondance</button>
                                    </form>
                                @else
                                    <button type="button" class="choose-loss font-semibold text-sonar-dark hover:underline"
                                            data-id="{{ $perte->id }}" data-title="{{ $perte->type_perte ?: 'Objet perdu' }}"
                                            data-photo="{{ $perte->photoUrl() }}" data-url="{{ route('public.declarations.show', $perte) }}">Choisir cette perte</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $pertes->links() }}</div>
        @endif

        @unless ($decouverte)
            <a id="continue-with-loss" href="{{ route('declarations.create') }}" class="mt-6 inline-block text-sm font-medium text-azur hover:underline">Continuer sans correspondance</a>
        @endunless
    </div>

    @unless ($decouverte)
        <script>
            document.querySelectorAll('.choose-loss').forEach(button => {
                button.addEventListener('click', () => {
                    const selected = { type: 'perte-choisie', id: Number(button.dataset.id), title: button.dataset.title, photo: button.dataset.photo, url: button.dataset.url };
                    if ('BroadcastChannel' in window) {
                        const channel = new BroadcastChannel('spotlight-pertes');
                        channel.postMessage(selected);
                        channel.close();
                    }
                    const status = document.getElementById('match-selection-status');
                    status.textContent = `${selected.title} #${selected.id} sélectionné. Revenez au formulaire déjà ouvert ou continuez ici.`;
                    status.classList.remove('hidden');
                    const link = document.getElementById('continue-with-loss');
                    link.href = `{{ route('declarations.create') }}?perte_id=${selected.id}`;
                    link.textContent = 'Continuer avec cette perte';
                });
            });
        </script>
    @endunless
</x-app-layout>
