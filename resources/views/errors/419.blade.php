<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session expirée - Spotlight</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nuit font-sans text-gray-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <div class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl">
            <p class="text-sm font-semibold text-alerte">Session expirée</p>
            <h1 class="mt-2 text-xl font-semibold">Veuillez recommencer</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">La page est restée ouverte trop longtemps. Revenez au formulaire puis envoyez-le de nouveau.</p>
            <a href="{{ url()->previous() }}" class="mt-6 inline-flex rounded-md bg-alerte px-4 py-2 text-sm font-semibold text-white hover:bg-alerte-dark">Revenir au formulaire</a>
        </div>
    </main>
</body>
</html>
