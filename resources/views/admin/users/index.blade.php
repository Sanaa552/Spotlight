<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                {{ __('Gestion des comptes') }}
            </h2>
            <a href="{{ route('admin.users.create') }}"
               class="inline-flex items-center px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark transition">
                Ajouter
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('warning'))
                <div class="bg-ambre/10 border border-ambre/40 text-ambre-dark px-4 py-3 rounded-lg">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($users as $user)
                            <tr x-data="{ selectedRole: '{{ $user->role?->value ?? 'citoyen' }}' }">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $user->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->telephone ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->isAdministrateur())
                                        <span class="text-xs font-semibold text-azur-dark">Super administrateur</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <select x-model="selectedRole"
                                                    class="text-xs border-gray-300 rounded-md focus:border-alerte focus:ring-alerte">
                                                <option value="citoyen" {{ ($user->role?->value ?? 'citoyen') === 'citoyen' ? 'selected' : '' }}>Citoyen</option>
                                                <option value="moderateur" {{ $user->role?->value === 'moderateur' ? 'selected' : '' }}>Modérateur</option>
                                            </select>
                                            <button type="button"
                                                    x-on:click="$dispatch('open-modal', 'change-role-{{ $user->id }}')"
                                                    class="text-xs font-semibold text-azur hover:text-azur-dark">
                                                Modifier
                                            </button>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->is_blocked)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-alerte/10 text-alerte-dark">Bloqué</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-sonar/15 text-sonar-dark">Actif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    @unless ($user->isAdministrateur())
                                        <button type="button"
                                                x-on:click="$dispatch('open-modal', 'resend-link-{{ $user->id }}')"
                                                class="text-xs font-medium text-azur hover:text-azur-dark">
                                            Renvoyer le lien
                                        </button>

                                        <button type="button"
                                                x-on:click="$dispatch('open-modal', 'toggle-block-{{ $user->id }}')"
                                                class="text-xs font-medium {{ $user->is_blocked ? 'text-sonar-dark hover:text-sonar' : 'text-laiton hover:text-laiton/80' }}">
                                            {{ $user->is_blocked ? 'Débloquer' : 'Bloquer' }}
                                        </button>

                                        <button type="button"
                                                x-on:click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')"
                                                class="text-xs font-medium text-alerte hover:text-alerte-dark">
                                            Supprimer
                                        </button>

                                        <x-modal name="change-role-{{ $user->id }}" maxWidth="md" focusable>
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="whitespace-normal p-6 text-left">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="role" x-bind:value="selectedRole">
                                                <input type="hidden" name="is_blocked" value="{{ $user->is_blocked ? 1 : 0 }}">
                                                <h2 class="text-lg font-semibold text-gray-900">Confirmer le changement de rôle</h2>
                                                <p class="mt-2 text-sm text-gray-600">
                                                    Le rôle du compte <strong>{{ $user->email }}</strong> sera modifié. Ses autorisations changeront immédiatement.
                                                </p>
                                                <div class="mt-6 flex justify-end gap-3">
                                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                                    <x-primary-button>Confirmer</x-primary-button>
                                                </div>
                                            </form>
                                        </x-modal>

                                        <x-modal name="resend-link-{{ $user->id }}" maxWidth="md" focusable>
                                            <form method="POST" action="{{ route('admin.users.send-reset', $user) }}" class="whitespace-normal p-6 text-left">
                                                @csrf
                                                <h2 class="text-lg font-semibold text-gray-900">Renvoyer le lien de réinitialisation ?</h2>
                                                <p class="mt-2 text-sm text-gray-600">
                                                    Un nouveau lien sera envoyé à <strong>{{ $user->email }}</strong>. Tout ancien lien deviendra inutilisable.
                                                </p>
                                                <div class="mt-6 flex justify-end gap-3">
                                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                                    <x-primary-button>Envoyer le lien</x-primary-button>
                                                </div>
                                            </form>
                                        </x-modal>

                                        <x-modal name="toggle-block-{{ $user->id }}" maxWidth="md" focusable>
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="whitespace-normal p-6 text-left">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="role" value="{{ $user->role?->value ?? 'citoyen' }}">
                                                <input type="hidden" name="is_blocked" value="{{ $user->is_blocked ? 0 : 1 }}">
                                                <h2 class="text-lg font-semibold text-gray-900">
                                                    {{ $user->is_blocked ? 'Débloquer ce compte ?' : 'Bloquer ce compte ?' }}
                                                </h2>
                                                <p class="mt-2 text-sm text-gray-600">
                                                    @if ($user->is_blocked)
                                                        <strong>{{ $user->email }}</strong> pourra de nouveau se connecter et utiliser Spotlight.
                                                    @else
                                                        <strong>{{ $user->email }}</strong> sera immédiatement déconnecté et ne pourra plus accéder aux fonctions protégées.
                                                    @endif
                                                </p>
                                                <div class="mt-6 flex justify-end gap-3">
                                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                                    <x-danger-button>{{ $user->is_blocked ? 'Débloquer' : 'Bloquer' }}</x-danger-button>
                                                </div>
                                            </form>
                                        </x-modal>

                                        <x-modal name="delete-user-{{ $user->id }}" maxWidth="md" focusable>
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="whitespace-normal p-6 text-left">
                                                @csrf
                                                @method('DELETE')
                                                <h2 class="text-lg font-semibold text-gray-900">Supprimer définitivement ce compte ?</h2>
                                                <p class="mt-2 text-sm text-gray-600">
                                                    Le compte <strong>{{ $user->email }}</strong> et les données qui en dépendent seront supprimés. Cette action est irréversible.
                                                </p>
                                                <div class="mt-6 flex justify-end gap-3">
                                                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                                    <x-danger-button>Supprimer définitivement</x-danger-button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $users->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
