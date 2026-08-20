<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Spotlight') }} — À propos</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-nuit">

    <x-public-nav />

    <main class="px-6 py-10 max-w-3xl mx-auto">

        <h1 class="text-2xl sm:text-3xl font-bold text-argent mb-2 text-center">À propos de Spotlight</h1>
        <p class="text-sm text-argent/50 text-center mb-12">Repérer, alerter, retrouver.</p>

        <div class="space-y-10 text-argent/70 text-sm leading-relaxed">

            <section>
                <h2 class="text-argent font-semibold text-lg mb-2">Notre mission</h2>
                <p>
                    Spotlight est une plateforme citoyenne de diffusion d'alerte en temps réel, dédiée aux
                    personnes et objets disparus au Cameroun. Notre objectif : réduire le temps entre une
                    disparition et sa résolution, en mobilisant la communauté autour de chaque déclaration.
                </p>
            </section>

            <section>
                <h2 class="text-argent font-semibold text-lg mb-2">Comment ça fonctionne</h2>
                <p>
                    Tout citoyen peut déclarer une perte ou une découverte, avec photo et localisation.
                    Chaque déclaration est vérifiée par un modérateur avant d'être diffusée publiquement,
                    afin de garantir la fiabilité des informations partagées. Une fois la situation résolue,
                    la déclaration est clôturée et devient un avis de restitution.
                </p>
            </section>

            <section>
                <h2 class="text-argent font-semibold text-lg mb-2">Ce que Spotlight n'est pas</h2>
                <p>
                    Spotlight est un outil de mobilisation citoyenne — il ne remplace en aucun cas la Police
                    ou la Gendarmerie Nationale. En cas de danger immédiat ou de disparition inquiétante,
                    contacte toujours en priorité les forces de l'ordre
                    (<a href="{{ route('public.declarations.index') }}" class="text-alerte hover:underline">voir les numéros d'urgence</a>).
                </p>
            </section>

            <section>
                <h2 class="text-argent font-semibold text-lg mb-2">Confidentialité</h2>
                <p>
                    Seules les déclarations validées par un modérateur sont visibles publiquement.
                    Les informations personnelles sensibles restent réservées aux utilisateurs connectés
                    et aux autorités compétentes.
                </p>
            </section>

        </div>

        <div class="mt-16 text-center">
            <a href="{{ route('register') }}"
               class="inline-flex items-center px-8 py-3 bg-alerte rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                Rejoindre la communauté
            </a>
        </div>

    </main>

    <footer class="border-t border-white/10 py-6 text-center mt-16">
        <p class="text-xs text-argent/40">
            &copy; {{ date('Y') }} {{ config('app.name', 'Spotlight') }}. Tous droits réservés.
        </p>
    </footer>

</body>
</html>