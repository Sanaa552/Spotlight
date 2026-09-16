<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fichiers trop volumineux - Spotlight</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nuit font-sans text-gray-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <div class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl">
            <h1 class="text-xl font-semibold">Fichiers trop volumineux</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                La taille totale envoyée dépasse la limite du serveur ({{ $postMaxSize }}). Sélectionnez des fichiers plus légers puis réessayez.
            </p>
            <p class="mt-2 text-sm text-gray-500">
                Si le problème persiste malgré des fichiers conformes, contactez l’administrateur.
            </p>
            <a href="{{ route('declarations.create') }}" class="mt-6 inline-flex items-center rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">
                Revenir à la déclaration
            </a>
        </div>
    </main>
</body>
</html>
