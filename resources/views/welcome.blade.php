<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Spotlight') }} — Repérer, alerter, retrouver</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-nuit">

    <!-- Nav publique -->
    <x-public-nav />
    <div class="h-[73px]"></div>

    <!-- Hero -->
    <section class="px-6 py-16 sm:py-24 flex flex-col items-center text-center">
        <img src="{{ asset('images/spotlight-logo-complet.png') }}"
             alt="Spotlight — Repérer, alerter, retrouver. Personnes disparues, objets disparus, alertes en temps réel."
             class="w-full max-w-2xl object-contain animate-spotlight-float">

        <p class="mt-8 max-w-xl text-argent/60 text-sm sm:text-base">
            Spotlight aide les citoyens à signaler une disparition ou une découverte, et diffuse l'alerte
            en temps réel jusqu'à ce que la situation soit résolue.
        </p>

        @guest
            <div class="mt-8 flex flex-col sm:flex-row items-center gap-4">
                <a href="{{ route('register') }}"
                   class="inline-flex items-center px-8 py-3 bg-alerte border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    Créer un compte
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-8 py-3 bg-transparent border border-argent/30 rounded-md font-semibold text-sm text-argent uppercase tracking-widest hover:border-argent hover:bg-white/5 transition">
                    Se connecter
                </a>
            </div>
        @endguest
    </section>

    <!-- Fonctionnalités -->
    <section class="px-6 pb-20">
        <div class="max-w-5xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-6">

            <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                <div class="w-10 h-10 rounded-full bg-alerte/15 flex items-center justify-center text-alerte text-lg mb-4">
                    🔍
                </div>
                <h3 class="text-argent font-semibold mb-2">Déclarer</h3>
                <p class="text-sm text-argent/60">
                    Signale une personne ou un objet perdu, ou une découverte, avec photo et localisation.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                <div class="w-10 h-10 rounded-full bg-ambre/15 flex items-center justify-center text-ambre text-lg mb-4">
                    🔔
                </div>
                <h3 class="text-argent font-semibold mb-2">Être alerté</h3>
                <p class="text-sm text-argent/60">
                    Reçois des notifications en temps réel dès qu'une correspondance ou une mise à jour survient.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                <div class="w-10 h-10 rounded-full bg-sonar/15 flex items-center justify-center text-sonar text-lg mb-4">
                    ✅
                </div>
                <h3 class="text-argent font-semibold mb-2">Retrouver</h3>
                <p class="text-sm text-argent/60">
                    Suis l'avancement de ta déclaration jusqu'à sa résolution, avec l'aide de la communauté.
                </p>
            </div>

        </div>
    </section>

        <!-- Comment ça marche -->
    <section class="px-6 pb-20">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-center text-argent font-semibold text-2xl mb-2">Comment ça marche</h2>
            <p class="text-center text-argent/50 text-sm mb-10">Trois étapes simples, du signalement à la résolution.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-alerte flex items-center justify-center text-white font-bold text-lg mb-4">1</div>
                    <h3 class="text-argent font-semibold mb-2">Tu déclares</h3>
                    <p class="text-sm text-argent/60">Décris la personne ou l'objet, ajoute une photo et le lieu concerné.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-laiton flex items-center justify-center text-white font-bold text-lg mb-4">2</div>
                    <h3 class="text-argent font-semibold mb-2">On vérifie</h3>
                    <p class="text-sm text-argent/60">Un modérateur valide ta déclaration avant sa diffusion publique.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-sonar flex items-center justify-center text-white font-bold text-lg mb-4">3</div>
                    <h3 class="text-argent font-semibold mb-2">La communauté agit</h3>
                    <p class="text-sm text-argent/60">Les citoyens reçoivent l'alerte et peuvent signaler une correspondance.</p>
                </div>
            </div>
        </div>
    </section>

        <!-- Témoignages (exemples illustratifs) -->
    <section class="px-6 pb-20">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-center text-argent font-semibold text-2xl mb-1">Ils utilisent Spotlight</h2>
            <p class="text-center text-argent/40 text-xs mb-10 uppercase tracking-wide">Exemples illustratifs</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">

                <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-alerte/20 border border-alerte/40 flex items-center justify-center text-alerte font-bold">
                            MN
                        </div>
                        <div>
                            <div class="text-argent font-semibold text-sm">Marie N.</div>
                            <div class="text-argent/40 text-xs">Douala</div>
                        </div>
                    </div>
                    <p class="text-sm text-argent/60 italic">
                        « J'ai pu signaler la disparition de mon frère en quelques minutes, avec sa photo
                        et le dernier endroit où on l'avait vu. La communauté a été très réactive. »
                    </p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-sonar/20 border border-sonar/40 flex items-center justify-center text-sonar font-bold">
                            PE
                        </div>
                        <div>
                            <div class="text-argent font-semibold text-sm">Paul E.</div>
                            <div class="text-argent/40 text-xs">Yaoundé</div>
                        </div>
                    </div>
                    <p class="text-sm text-argent/60 italic">
                        « J'ai trouvé un sac contenant des papiers importants. Grâce à Spotlight, j'ai pu
                        signaler la découverte et retrouver son propriétaire le jour même. »
                    </p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-lg p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-full bg-laiton/20 border border-laiton/40 flex items-center justify-center text-laiton font-bold">
                            AF
                        </div>
                        <div>
                            <div class="text-argent font-semibold text-sm">Aïcha F.</div>
                            <div class="text-argent/40 text-xs">Garoua</div>
                        </div>
                    </div>
                    <p class="text-sm text-argent/60 italic">
                        « L'application est simple à utiliser et les notifications m'ont permis de suivre
                        l'évolution du dossier étape par étape jusqu'à sa résolution. »
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- Numéros d'urgence -->
    <section class="px-6 pb-20">
        <div class="max-w-4xl mx-auto bg-white/5 border border-alerte/20 rounded-lg p-6 sm:p-8">
            <h2 class="text-argent font-semibold text-xl mb-1">🚨 En cas d'urgence immédiate</h2>
            <p class="text-sm text-argent/60 mb-6">
                Spotlight complète les canaux officiels — il ne les remplace pas. En cas de danger immédiat
                ou de disparition inquiétante, contacte directement les forces de l'ordre.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <a href="tel:117" class="block bg-white/5 hover:bg-white/10 rounded-lg p-4 text-center transition">
                    <div class="text-2xl mb-1">👮</div>
                    <div class="text-xs text-argent/50 uppercase tracking-wide">Police</div>
                    <div class="text-argent font-bold text-lg">117</div>
                </a>
                <a href="tel:113" class="block bg-white/5 hover:bg-white/10 rounded-lg p-4 text-center transition">
                    <div class="text-2xl mb-1">🪖</div>
                    <div class="text-xs text-argent/50 uppercase tracking-wide">Gendarmerie</div>
                    <div class="text-argent font-bold text-lg">113</div>
                </a>
                <a href="tel:118" class="block bg-white/5 hover:bg-white/10 rounded-lg p-4 text-center transition">
                    <div class="text-2xl mb-1">🚒</div>
                    <div class="text-xs text-argent/50 uppercase tracking-wide">Pompiers</div>
                    <div class="text-argent font-bold text-lg">118</div>
                </a>
                <a href="tel:119" class="block bg-white/5 hover:bg-white/10 rounded-lg p-4 text-center transition">
                    <div class="text-2xl mb-1">🚑</div>
                    <div class="text-xs text-argent/50 uppercase tracking-wide">SAMU</div>
                    <div class="text-argent font-bold text-lg">119</div>
                </a>
            </div>

            <p class="text-xs text-argent/40 mt-6">
                Numéros valables depuis un téléphone mobile (Yaoundé, Douala, Garoua). Depuis un poste fixe :
                Police 17 · Gendarmerie 13 · Pompiers 18 · SAMU 19. Vérifie aussi les numéros affichés au
                commissariat/à la brigade de ta localité, certaines régions ayant des lignes locales dédiées.
            </p>
        </div>
    </section>

    <!-- Zone d'action -->
    <section class="px-6 pb-20">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-argent font-semibold text-xl mb-2">📍 Présent partout au Cameroun</h2>
            <p class="text-sm text-argent/60 max-w-xl mx-auto">
                Spotlight est ouvert à toutes les villes et régions du Cameroun — de Yaoundé et Douala
                aux zones rurales les plus reculées. Toute déclaration est visible par l'ensemble
                des citoyens inscrits, où qu'ils se trouvent.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @foreach (['Yaoundé', 'Douala', 'Garoua', 'Bafoussam', 'Bamenda', 'Maroua', 'Ngaoundéré', 'Bertoua', 'Ebolowa', 'Buea'] as $ville)
                    <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-full text-xs text-argent/60">{{ $ville }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="border-t border-white/10 py-6 text-center">
        <p class="text-xs text-argent/40">
            &copy; {{ date('Y') }} {{ config('app.name', 'Spotlight') }}. Tous droits réservés.
        </p>
    </footer>

</body>
</html>