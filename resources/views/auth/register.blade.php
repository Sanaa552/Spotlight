<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
        @csrf

        <!-- Name -->
        <div class="w-full">
            <x-input-label for="name" :value="__('Nom')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="w-full">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
                <!-- Téléphone -->
        <div class="w-full">
            <x-input-label for="telephone" :value="__('Téléphone')" />
            <x-text-input id="telephone" class="block mt-1 w-full" type="tel" name="telephone" :value="old('telephone')" required autocomplete="tel" placeholder="+237 6XX XX XX XX" />
            <x-input-error :messages="$errors->get('telephone')" class="mt-2" />
        </div>

       <!-- Password -->
        <div class="w-full">
            <x-input-label for="password" :value="__('Mot de passe')" />

            <div class="relative">
                <x-text-input id="password"
                    class="block mt-1 w-full pr-12"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password" />

                <button type="button"
                    onclick="togglePassword('password', this)"
                    class="absolute inset-y-0 right-0 flex items-center px-3 mt-1 text-gray-500 hover:text-alerte focus:outline-none"
                    aria-label="Afficher le mot de passe">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="w-5 h-5 eye-icon"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

       <!-- Confirm Password -->
<div class="w-full">
    <x-input-label for="password_confirmation" :value="__('Confirmer le mot de passe')" />

    <div class="relative">
        <x-text-input id="password_confirmation"
            class="block mt-1 w-full pr-12"
            type="password"
            name="password_confirmation"
            required
            autocomplete="new-password" />

        <button type="button"
            onclick="togglePassword('password_confirmation', this)"
            class="absolute inset-y-0 right-0 flex items-center px-3 mt-1 text-gray-500 hover:text-alerte focus:outline-none"
            aria-label="Afficher le mot de passe">

            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5 eye-icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268-2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
        </button>
    </div>

    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
</div>

        <div class="flex items-center justify-end">
            <x-primary-button>
                {{ __("S'inscrire") }}
            </x-primary-button>
        </div>

        <p class="text-center text-sm text-gray-600">
            {{ __("Déjà un compte ?") }}
            <a href="{{ route('login') }}" class="font-semibold text-alerte hover:underline">
                {{ __('Se connecter') }}
            </a>
        </p>
    </form>
</x-guest-layout>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('.eye-icon');

    if (input.type === 'password') {
        input.type = 'text';
        button.setAttribute('aria-label', 'Masquer le mot de passe');
        icon.innerHTML = `
            <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.223-3.592M6.6 6.6A9.96 9.96 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.132 5.066M6.6 6.6L3 3m3.6 3.6l4.278 4.278m0 0a3 3 0 104.243 4.243M12.878 12.878L21 21" />
        `;
    } else {
        input.type = 'password';
        button.setAttribute('aria-label', 'Afficher le mot de passe');
        icon.innerHTML = `
            <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        `;
    }
}
</script>