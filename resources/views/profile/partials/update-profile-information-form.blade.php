<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')
         <!-- photo -->
                  <div class="flex items-center gap-4">
            @if ($user->photoUrl())
                <img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-full object-cover">
            @else
                <span class="w-16 h-16 rounded-full bg-azur/15 text-azur font-bold flex items-center justify-center">
                    {{ $user->initiales() }}
                </span>
            @endif

            <div class="flex-1">
                <x-input-label for="photo" value="Photo de profil" />
                <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png"
                       class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-azur/10 file:text-azur hover:file:bg-azur/20" />
                <x-input-error class="mt-2" :messages="$errors->get('photo')" />
            </div>
        </div>
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="telephone" value="Téléphone (facultatif)" />
            <x-text-input id="telephone" name="telephone" type="tel" class="mt-1 block w-full"
                          :value="old('telephone', $user->telephone)" autocomplete="tel" maxlength="20" />
            <x-input-error class="mt-2" :messages="$errors->get('telephone')" />
        </div>

        @if ($user->isCitoyen())
            <div>
                <input type="hidden" name="new_declaration_email" value="0">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="new_declaration_email" value="1"
                           @checked(old('new_declaration_email', $user->new_declaration_email))
                           class="mt-1 rounded border-gray-300 text-azur focus:ring-azur">
                    <span>Recevoir un email quand une nouvelle déclaration est publiée</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('new_declaration_email')" />
            </div>
        @else
            <input type="hidden" name="new_declaration_email" value="{{ $user->new_declaration_email ? 1 : 0 }}">
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
