<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" x-data="{ showPassword: false, showConfirmation: false }">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" required />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Nouveau mot de passe')" required />
            <div class="relative">
                <x-text-input id="password" class="block mt-1 w-full pr-12" type="password" x-bind:type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password" />
                <button type="button" x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                    x-bind:aria-pressed="showPassword"
                    aria-label="Afficher le mot de passe" title="Afficher ou masquer le mot de passe"
                    class="absolute inset-y-0 right-0 mt-1 flex min-h-10 w-11 items-center justify-center text-gray-500 hover:text-alerte focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-alerte">
                    <x-icon name="eye" class="h-5 w-5" x-show="!showPassword" aria-hidden="true" />
                    <x-icon name="eye-off" class="h-5 w-5" x-show="showPassword" x-cloak aria-hidden="true" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirmer le mot de passe')" required />

            <div class="relative">
                <x-text-input id="password_confirmation" class="block mt-1 w-full pr-12"
                    type="password" x-bind:type="showConfirmation ? 'text' : 'password'"
                    name="password_confirmation" required autocomplete="new-password" />
                <button type="button" x-on:click="showConfirmation = !showConfirmation"
                    x-bind:aria-label="showConfirmation ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                    x-bind:aria-pressed="showConfirmation"
                    aria-label="Afficher le mot de passe" title="Afficher ou masquer le mot de passe"
                    class="absolute inset-y-0 right-0 mt-1 flex min-h-10 w-11 items-center justify-center text-gray-500 hover:text-alerte focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-alerte">
                    <x-icon name="eye" class="h-5 w-5" x-show="!showConfirmation" aria-hidden="true" />
                    <x-icon name="eye-off" class="h-5 w-5" x-show="showConfirmation" x-cloak aria-hidden="true" />
                </button>
            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Réinitialiser') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
