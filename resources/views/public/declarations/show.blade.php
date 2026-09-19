<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Déclaration #{{ $declaration->id }} - {{ config('app.name', 'Spotlight') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-nuit text-argent">
    <x-public-nav />

    <main class="mx-auto max-w-4xl px-4 pb-16 pt-8 sm:px-6">
        <a href="{{ route('public.declarations.index') }}" class="text-sm text-argent/70 hover:text-argent">&larr; Avis de recherche</a>

        <article class="mt-6 border-b border-white/10 pb-8">
            <div class="flex flex-wrap items-center gap-3 text-sm text-argent/60">
                <span class="font-semibold {{ $declaration->type === 'perte' ? 'text-alerte' : 'text-sonar' }}">{{ $declaration->type === 'perte' ? 'Perte' : 'Découverte' }}</span>
                <span>Déclaration #{{ $declaration->id }}</span>
                <span>{{ $declaration->created_at->diffForHumans() }}</span>
                @if ($declaration->statut === 'cloturee')
                    <span class="text-sonar">Restituée</span>
                @endif
            </div>
            <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">{{ $declaration->type_perte ?? $declaration->type_decouverte ?? ucfirst($declaration->categorie) }}</h1>
            @if ($declaration->lieu)
                <p class="mt-2 text-sm text-argent/70">Secteur : {{ $declaration->lieu }}</p>
            @endif
            @if ($declaration->photoUrl())
                <img src="{{ $declaration->photoUrl() }}" alt="Photo publique de la déclaration #{{ $declaration->id }}"
                     class="mt-6 max-h-[560px] w-full bg-white/5 object-contain">
            @endif
            <p class="mt-6 whitespace-pre-line text-sm leading-7 text-argent/90">{{ $declaration->description }}</p>
            @if ($declaration->type === 'perte' && $declaration->categorie === 'objet' && $declaration->statut === 'validee')
                <a href="{{ route('declarations.create', ['perte_id' => $declaration->id]) }}"
                   class="mt-5 inline-flex rounded-md bg-sonar px-4 py-2 text-sm font-semibold text-white hover:bg-sonar-dark">J’ai retrouvé cet objet</a>
            @endif
            <div class="mt-6 flex flex-wrap gap-4 text-sm font-medium text-azur">
                @if ($declaration->facebook_post_url)
                    <a href="{{ $declaration->facebook_post_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Voir sur Facebook</a>
                @endif
                @if ($declaration->instagram_post_url)
                    <a href="{{ $declaration->instagram_post_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Voir sur Instagram</a>
                @endif
            </div>
        </article>

        <section id="discussion" class="scroll-mt-24 pt-8" x-data="{ replyTo: '', replyName: '' }">
            <h2 class="text-xl font-semibold">Informations et échanges</h2>
            @if (session('success'))
                <p class="mt-4 border-l-2 border-sonar pl-3 text-sm text-sonar">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <p class="mt-4 border-l-2 border-alerte pl-3 text-sm text-alerte" role="alert">{{ $errors->first() }}</p>
            @endif

            <div class="mt-6 divide-y divide-white/10 border-y border-white/10">
                @forelse ($declaration->commentaires as $commentaire)
                    <div class="py-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold">{{ $commentaire->auteur->name }}</span>
                            <time class="text-xs text-argent/50">{{ $commentaire->created_at->diffForHumans() }}</time>
                        </div>
                        @if ($commentaire->parent)
                            <p class="mt-1 text-xs text-azur">En réponse à {{ $commentaire->parent->auteur?->name ?? 'un membre' }}</p>
                        @endif
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-argent/80">{{ $commentaire->contenu }}</p>
                        @auth
                            <button type="button" data-author="{{ $commentaire->auteur->name }}"
                                    x-on:click="replyTo = '{{ $commentaire->id }}'; replyName = $el.dataset.author; $nextTick(() => $refs.commentInput.focus())"
                                    class="mt-2 text-xs font-medium text-azur hover:underline">Répondre</button>
                        @endauth
                    </div>
                @empty
                    <p class="py-5 text-sm text-argent/60">Aucune information partagée pour le moment.</p>
                @endforelse
            </div>

            @auth
                @if (auth()->user()->hasVerifiedEmail() && ! auth()->user()->is_blocked)
                    <form method="POST" action="{{ route('declarations.commenter', $declaration) }}" class="mt-6 space-y-3">
                        @csrf
                        <input type="hidden" name="parent_id" x-bind:value="replyTo">
                        <div x-show="replyTo" x-cloak class="flex items-center gap-3 text-xs text-azur">
                            <span>Réponse à <span x-text="replyName"></span></span>
                            <button type="button" x-on:click="replyTo = ''; replyName = ''" class="text-argent/60 hover:text-argent">Annuler</button>
                        </div>
                        <label for="comment-input" class="block text-sm font-medium">Partager une information</label>
                        <textarea id="comment-input" name="contenu" required maxlength="1000" rows="3" x-ref="commentInput"
                                  class="w-full rounded-md border-white/20 bg-white text-gray-900 focus:border-azur focus:ring-azur"></textarea>
                        <button type="submit" class="rounded-md bg-alerte px-5 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">Publier</button>
                    </form>
                @else
                    <p class="mt-6 text-sm text-argent/70">Vérifiez votre compte pour participer à la discussion.</p>
                @endif
            @else
                <a href="{{ route('login') }}" class="mt-6 inline-block rounded-md bg-alerte px-5 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">Se connecter pour partager une information</a>
            @endauth
        </section>
    </main>
</body>
</html>
