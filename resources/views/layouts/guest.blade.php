<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
       
       <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen bg-nuit">
        <header class="border-b border-white/10 bg-nuit px-4 py-2.5 sm:absolute sm:inset-x-0 sm:top-0 sm:z-10 sm:px-8 sm:py-4">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <a href="/" class="flex items-center gap-2" aria-label="Retour à l'accueil">
                    <img src="{{ asset('images/spotlight-icon.png') }}" alt="" class="h-10 w-10 object-contain sm:h-12 sm:w-12">
                    <x-spotlight-wordmark size="text-xl sm:text-2xl" />
                </a>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />
                    <a href="/" class="text-sm font-medium text-argent/70 transition hover:text-argent">Accueil</a>
                </div>
            </div>
        </header>

        <main class="flex min-h-[calc(100vh-61px)] items-start justify-center px-5 py-6 sm:min-h-screen sm:items-center sm:px-6 sm:py-24">
            <div class="spotlight-form-panel w-full max-w-md border border-transparent bg-white px-5 py-5 shadow-sm sm:rounded-lg sm:px-6 sm:shadow-xl">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
