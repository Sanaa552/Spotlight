<x-guest-layout>
    <div class="mb-5 text-center">
        <h1 class="text-xl font-semibold text-gray-900">Vérifiez votre adresse e-mail</h1>
        <p class="mt-2 text-sm leading-6 text-gray-600">
            Un lien vient d’être envoyé à <strong>{{ auth()->user()->email }}</strong>. Cliquez dessus pour accéder à Spotlight.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-md border border-sonar/30 bg-sonar/10 px-4 py-3 text-sm font-medium text-sonar-dark">
            Un nouveau lien de vérification a été envoyé.
        </div>
    @endif

    <x-input-error :messages="$errors->get('email')" class="mb-4" />

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Renvoyer le lien') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Se déconnecter') }}
            </button>
        </form>
    </div>
</x-guest-layout>
