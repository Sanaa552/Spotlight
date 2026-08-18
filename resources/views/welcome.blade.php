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
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">

        <img src="{{ asset('images/spotlight-logo-complet.png') }}"
             alt="Spotlight — Repérer, alerter, retrouver. Personnes disparues, objets disparus, alertes en temps réel."
             class="w-full max-w-2xl object-contain">

        <div class="mt-10 flex flex-col sm:flex-row items-center gap-4">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center px-8 py-3 bg-alerte border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    Accéder au tableau de bord
                </a>
            @else
                <a href="{{ route('register') }}"
                   class="inline-flex items-center px-8 py-3 bg-alerte border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                    S'inscrire
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-8 py-3 bg-transparent border border-argent/30 rounded-md font-semibold text-sm text-argent uppercase tracking-widest hover:border-argent hover:bg-white/5 transition">
                    Se connecter
                </a>
            @endauth
        </div>

        <p class="mt-12 text-xs text-argent/40">
            &copy; {{ date('Y') }} {{ config('app.name', 'Spotlight') }}. Tous droits réservés.
        </p>

    </div>
</body>
</html>