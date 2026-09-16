<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé - Spotlight</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nuit font-sans text-gray-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <div class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl">
            <p class="text-sm font-semibold text-alerte">Erreur 403</p>
            <h1 class="mt-2 text-xl font-semibold">Accès refusé</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">Votre compte ne possède pas l’autorisation nécessaire pour consulter cette page ou effectuer cette action.</p>
            <a href="{{ url()->previous() }}" class="mt-6 inline-flex rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">Revenir en arrière</a>
        </div>
    </main>
</body>
</html>
