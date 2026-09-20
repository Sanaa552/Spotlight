<x-guest-layout>
    <div class="mb-5 text-center">
        <h1 class="text-xl font-semibold text-gray-900">Vérifiez votre adresse e-mail</h1>
        <p class="mt-2 text-sm leading-6 text-gray-600">
            Un lien de vérification a été demandé pour <strong>{{ auth()->user()->email }}</strong>.
            Ouvrez-le depuis votre messagerie pour accéder à Spotlight.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-md border border-sonar/30 bg-sonar/10 px-4 py-3 text-sm font-medium text-sonar-dark">
            La demande d’envoi a été prise en compte. Vérifiez votre boîte de réception et vos courriers indésirables.
        </div>
    @elseif (session('status') == 'verification-address-updated')
        <div class="mb-4 rounded-md border border-sonar/30 bg-sonar/10 px-4 py-3 text-sm font-medium text-sonar-dark">
            Votre adresse a été corrigée et un nouveau lien de vérification a été demandé.
        </div>
    @endif

    <x-input-error :messages="$errors->get('email')" class="mb-4" />

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
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

    @if (auth()->user()->isCitoyen())
        <details class="mt-4 border-t border-gray-200 pt-4" @if ($errors->emailCorrection->any()) open @endif>
            <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                Adresse e-mail incorrecte ? <span class="font-medium underline">La modifier</span>
            </summary>

            <form method="POST" action="{{ route('verification.address.update') }}" class="mt-4">
                @csrf
                @method('PATCH')

                <x-input-label for="email" value="Nouvelle adresse e-mail" />
                <x-text-input id="email" name="email" type="email" required autocomplete="email"
                              class="mt-1 block w-full" :value="old('email', auth()->user()->email)" />
                <x-input-error :messages="$errors->emailCorrection->get('email')" class="mt-2" />
                <x-primary-button class="mt-3">Enregistrer et renvoyer le lien</x-primary-button>
            </form>
        </details>
    @endif
</x-guest-layout>
