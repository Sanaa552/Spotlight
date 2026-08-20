<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Gestion des comptes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-sonar/10 border border-sonar/30 text-sonar-dark px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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
                            <tr>
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
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_blocked" value="{{ $user->is_blocked ? 1 : 0 }}">
                                        <select name="role" onchange="this.form.submit()"
                                                class="text-xs border-gray-300 rounded-md focus:border-alerte focus:ring-alerte">
                                            <option value="citoyen" {{ $user->role->value === 'citoyen' ? 'selected' : '' }}>Citoyen</option>
                                            <option value="moderateur" {{ $user->role->value === 'moderateur' ? 'selected' : '' }}>Modérateur</option>
                                            <option value="administrateur" {{ $user->role->value === 'administrateur' ? 'selected' : '' }}>Administrateur</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($user->is_blocked)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-alerte/10 text-alerte-dark">Bloqué</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-sonar/15 text-sonar-dark">Actif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                                        <input type="hidden" name="is_blocked" value="{{ $user->is_blocked ? 0 : 1 }}">
                                        <button type="submit" class="text-xs font-medium {{ $user->is_blocked ? 'text-sonar-dark hover:text-sonar' : 'text-laiton hover:text-laiton/80' }}">
                                            {{ $user->is_blocked ? 'Débloquer' : 'Bloquer' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                                          onsubmit="return confirm('Supprimer définitivement ce compte ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-alerte hover:text-alerte-dark">
                                            Supprimer
                                        </button>
                                    </form>
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