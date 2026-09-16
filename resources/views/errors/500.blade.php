<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erreur technique - Spotlight</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nuit font-sans text-gray-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <div class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl">
            <p class="text-sm font-semibold text-alerte">Erreur technique</p>
            <h1 class="mt-2 text-xl font-semibold">Une action n’a pas pu aboutir</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">Réessayez dans quelques instants. Si le problème persiste, contactez l’administrateur Spotlight.</p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">Retour à l’accueil</a>
        </div>
    </main>
</body>
</html>
