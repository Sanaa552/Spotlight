<x-guest-layout>
    <div class="mb-5 text-center">
        <h1 class="text-xl font-semibold text-gray-900">Complétez votre profil</h1>
        <p class="mt-1 text-sm text-gray-600">Vos coordonnées permettront à Spotlight de vous contacter au sujet de vos déclarations.</p>
    </div>

    <form method="POST" action="{{ route('facebook.profile.update') }}" class="space-y-4">
        @csrf
        @method('PATCH')

        <div>
            <x-input-label for="facebook_name" value="Nom" />
            <x-text-input id="facebook_name" type="text" class="mt-1 block w-full" :value="$user->name" disabled />
        </div>

        @if (blank($user->email))
            <div>
                <x-input-label for="email" value="Adresse e-mail" required />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                              :value="old('email')" required autocomplete="email" />
                <p class="mt-1 text-xs text-gray-600">Un lien de vérification sera envoyé à cette adresse.</p>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
        @else
            <div>
                <x-input-label for="facebook_email" value="Adresse e-mail" />
                <x-text-input id="facebook_email" type="email" class="mt-1 block w-full" :value="$user->email" disabled />
            </div>
        @endif

        <div>
            <x-input-label for="telephone" value="Numéro de téléphone" required />
            <x-text-input id="telephone" name="telephone" type="tel" class="mt-1 block w-full"
                          :value="old('telephone', $user->telephone)" required autocomplete="tel"
                          placeholder="+237690000000" />
            <p class="mt-1 text-xs leading-5 text-gray-600">Indiquez de préférence votre numéro WhatsApp. Si vous n’utilisez pas WhatsApp, indiquez un numéro sur lequel nous pouvons vous appeler.</p>
            <x-input-error :messages="$errors->get('telephone')" class="mt-1" />
        </div>

        <x-primary-button class="w-full justify-center">Enregistrer et continuer</x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">Se déconnecter</button>
    </form>
</x-guest-layout>
