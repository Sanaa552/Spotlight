<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-argent">Déclarations publiées</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-4 border-b border-white/15 pb-3 text-sm font-medium">
                <a href="{{ route('moderation.index') }}" class="pb-2 text-argent/70 hover:text-white">En attente</a>
                <span class="border-b-2 border-alerte pb-2 text-white">Publiées et rappels</span>
            </div>

            @if (session('success'))
                <p class="rounded-md border border-sonar/30 bg-sonar/10 px-4 py-3 text-sm text-sonar-dark" role="status">{{ session('success') }}</p>
            @endif
            @if (session('warning'))
                <p class="rounded-md border border-laiton/30 bg-laiton/10 px-4 py-3 text-sm text-laiton" role="alert">{{ session('warning') }}</p>
            @endif
            @if ($errors->any())
                <p class="rounded-md border border-alerte/30 bg-alerte/10 px-4 py-3 text-sm text-alerte-dark" role="alert">{{ $errors->first() }}</p>
            @endif

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($declarations as $declaration)
                @php
                    $pendingChannels = $declaration->publicationReminders
                        ->whereIn('status', ['queued', 'processing'])->pluck('channel')->all();
                @endphp
                <article x-data="{}" class="min-w-0 rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex min-w-0 items-start gap-3">
                        <img src="{{ $declaration->photoUrl() }}"
                             alt="Photo publique de la déclaration #{{ $declaration->id }}"
                             loading="lazy" width="72" height="72" class="h-[72px] w-[72px] shrink-0 rounded bg-gray-100 object-cover">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-type-badge :type="$declaration->type" />
                                <x-status-badge :statut="$declaration->statut" />
                                <span class="text-xs text-gray-500">#{{ $declaration->id }}</span>
                            </div>
                            <h3 class="mt-1 truncate text-sm font-semibold text-gray-900" title="{{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}">{{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}</h3>
                            <p class="mt-0.5 line-clamp-1 break-words text-xs text-gray-600">{{ $declaration->description }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ $declaration->citoyen->name }} · {{ $declaration->created_at->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-100 pt-2 text-xs">
                        <span class="font-medium text-gray-500">Posts initiaux</span>
                        @if ($declaration->facebook_post_url)
                            <a href="{{ $declaration->facebook_post_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-azur hover:underline">Facebook ↗</a>
                        @else
                            <span class="text-gray-400">Facebook indisponible</span>
                        @endif
                        @if ($declaration->instagram_post_url)
                            <a href="{{ $declaration->instagram_post_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-azur hover:underline">Instagram ↗</a>
                        @else
                            <span class="text-gray-400">Instagram indisponible</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <a href="{{ route('moderation.declarations.show', $declaration) }}" class="text-xs font-medium text-azur hover:underline">Voir le dossier</a>
                        <button type="button" x-on:click="$dispatch('open-modal', 'new-reminder-{{ $declaration->id }}')"
                                @disabled(count($pendingChannels) === 2)
                                class="inline-flex shrink-0 items-center gap-1.5 rounded bg-alerte px-3 py-2 text-xs font-semibold text-white hover:bg-alerte-dark disabled:cursor-not-allowed disabled:opacity-50">
                            <x-icon name="refresh-cw" class="h-3.5 w-3.5" />
                            Publier un rappel
                        </button>
                    </div>

                    @if ($declaration->publicationReminders->isNotEmpty())
                        <details class="mt-3 border-t border-gray-100 pt-2">
                            <summary class="cursor-pointer text-xs font-medium text-gray-600">Historique des rappels ({{ $declaration->publicationReminders->count() }})</summary>
                            <div class="divide-y divide-gray-100">
                                @foreach ($declaration->publicationReminders as $reminder)
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-xs text-gray-600">
                                        <span class="font-semibold text-gray-900">{{ ucfirst($reminder->channel) }}</span>
                                        <span>{{ $reminder->created_at->format('d/m/Y H:i') }}</span>
                                        <span>{{ $reminder->auteur?->name ?? 'Compte supprimé' }}</span>
                                        <span @class([
                                            'font-medium',
                                            'text-sonar-dark' => $reminder->status === 'succeeded',
                                            'text-alerte-dark' => $reminder->status === 'failed',
                                            'text-azur' => in_array($reminder->status, ['queued', 'processing'], true),
                                        ])>{{ match ($reminder->status) {
                                            'succeeded' => 'Publié',
                                            'failed' => 'Échec',
                                            'processing' => 'En cours',
                                            default => 'En attente',
                                        } }}</span>
                                        @if ($reminder->post_url)
                                            <a href="{{ $reminder->post_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-azur hover:underline">Voir le post</a>
                                        @endif
                                        @if ($reminder->error)
                                            <span class="text-alerte-dark">{{ $reminder->error }}</span>
                                        @endif
                                        @if ($reminder->status === 'failed')
                                            <button type="button" x-on:click="$dispatch('open-modal', 'retry-reminder-{{ $reminder->id }}')"
                                                    class="font-medium text-azur hover:underline">Réessayer</button>
                                            <x-modal name="retry-reminder-{{ $reminder->id }}" maxWidth="md" focusable>
                                                <form method="POST" action="{{ route('moderation.reminders.retry', $reminder) }}" class="p-6">
                                                    @csrf
                                                    <h3 class="text-base font-semibold text-gray-900">Relancer ce rappel ?</h3>
                                                    <p class="mt-2 text-sm text-gray-600">Seul {{ ucfirst($reminder->channel) }} sera repris. Une publication déjà confirmée ne sera pas envoyée une seconde fois.</p>
                                                    <div class="mt-5 flex justify-end gap-3">
                                                        <button type="button" x-on:click="$dispatch('close-modal', 'retry-reminder-{{ $reminder->id }}')" class="px-3 py-2 text-sm text-gray-600">Annuler</button>
                                                        <button type="submit" class="rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white">Relancer</button>
                                                    </div>
                                                </form>
                                            </x-modal>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </article>

                <x-modal name="new-reminder-{{ $declaration->id }}" maxWidth="md" focusable>
                    <form method="POST" action="{{ route('moderation.reminders.store', $declaration) }}"
                          x-data="{ selection: '', submitting: false }" x-on:submit="submitting = true" class="p-6">
                        @csrf
                        <h3 class="text-base font-semibold text-gray-900">Publier un rappel pour le dossier #{{ $declaration->id }} ?</h3>
                        <p class="mt-2 text-sm text-gray-600">Choisissez où publier ce nouveau post. Les publications initiales resteront inchangées.</p>
                        <fieldset class="mt-4 divide-y divide-gray-100 border-y border-gray-100">
                            <legend class="text-sm font-semibold text-gray-900">Réseau du rappel</legend>
                            <label class="flex items-center gap-3 py-3 text-sm text-gray-700 has-[:disabled]:text-gray-400">
                                <input type="radio" name="selection" required x-model="selection" value="facebook" @disabled(in_array('facebook', $pendingChannels))
                                       class="border-gray-300 text-alerte focus:ring-alerte">
                                Facebook seul @if (in_array('facebook', $pendingChannels))<span class="text-xs">(déjà en cours)</span>@endif
                            </label>
                            <label class="flex items-center gap-3 py-3 text-sm text-gray-700 has-[:disabled]:text-gray-400">
                                <input type="radio" name="selection" required x-model="selection" value="instagram" @disabled(in_array('instagram', $pendingChannels))
                                       class="border-gray-300 text-alerte focus:ring-alerte">
                                Instagram seul @if (in_array('instagram', $pendingChannels))<span class="text-xs">(déjà en cours)</span>@endif
                            </label>
                            <label class="flex items-center gap-3 py-3 text-sm text-gray-700 has-[:disabled]:text-gray-400">
                                <input type="radio" name="selection" required x-model="selection" value="both" @disabled(count($pendingChannels) > 0)
                                       class="border-gray-300 text-alerte focus:ring-alerte">
                                Facebook et Instagram @if (count($pendingChannels) > 0)<span class="text-xs">(un rappel est en cours)</span>@endif
                            </label>
                        </fieldset>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" x-on:click="$dispatch('close-modal', 'new-reminder-{{ $declaration->id }}')" class="px-3 py-2 text-sm text-gray-600">Annuler</button>
                            <button type="submit" x-bind:disabled="submitting || !selection" class="rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Confirmer la publication</span>
                                <span x-show="submitting">Envoi en cours…</span>
                            </button>
                        </div>
                    </form>
                </x-modal>
            @empty
                <p class="col-span-full py-10 text-center text-sm text-argent/70">Aucune déclaration publique à rappeler.</p>
            @endforelse
            </div>

            {{ $declarations->links() }}
        </div>
    </div>
</x-app-layout>
