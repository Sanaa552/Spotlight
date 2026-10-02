<section class="space-y-5">
    <header>
        <h2 class="text-lg font-medium text-gray-900">Supprimer le compte</h2>
        <p class="mt-1 text-sm text-gray-600">Confirmez votre identité avant toute suppression. Si votre compte contient des déclarations ou des interventions, contactez l’administration pour organiser leur conservation.</p>
    </header>

    <x-input-error :messages="$errors->userDeletion->get('user')" />
    <x-input-error :messages="$errors->userDeletion->get('facebook')" />

    @if ($user->canConfirmDeletionWithPassword())
        <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">Supprimer avec mon mot de passe</x-danger-button>

        <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->has('password')" focusable>
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')
                <input type="hidden" name="confirmation_method" value="password">
                <h2 class="text-lg font-medium text-gray-900">Supprimer définitivement mon compte ?</h2>
                <p class="mt-1 text-sm text-gray-600">Saisissez votre mot de passe Spotlight actuel pour confirmer.</p>
                <div class="mt-6">
                    <x-input-label for="delete_password" value="Mot de passe actuel" required />
                    <x-text-input id="delete_password" name="password" type="password" required autocomplete="current-password" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-danger-button>Supprimer définitivement</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($user->canConfirmDeletionWithFacebook())
        <form method="post" action="{{ route('profile.facebook.delete.redirect') }}">
            @csrf
            <x-secondary-button type="submit">Confirmer mon identité avec Facebook</x-secondary-button>
        </form>

        @if ($facebookDeletionConfirmed)
            <x-danger-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-facebook-deletion')">Continuer la suppression</x-danger-button>
            <x-modal name="confirm-facebook-deletion" :show="session('facebook_delete_confirmed') || $errors->userDeletion->has('confirm_delete')" focusable>
                <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                    @csrf
                    @method('delete')
                    <input type="hidden" name="confirmation_method" value="facebook">
                    <h2 class="text-lg font-medium text-gray-900">Supprimer définitivement mon compte ?</h2>
                    <p class="mt-1 text-sm text-gray-600">Votre identité Facebook a été confirmée. Cette étape ne peut pas être annulée après validation.</p>
                    <label class="mt-5 flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="confirm_delete" value="1" required class="mt-1 rounded border-gray-300">
                        <span>Je confirme vouloir supprimer définitivement mon compte Spotlight.</span>
                    </label>
                    <x-input-error :messages="$errors->userDeletion->get('confirm_delete')" class="mt-2" />
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-danger-button>Supprimer définitivement</x-danger-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endif
</section>
