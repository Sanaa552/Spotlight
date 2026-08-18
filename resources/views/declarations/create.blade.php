<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nouvelle déclaration') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 md:p-8">

                @if ($errors->any())
                    <div class="mb-6 bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside text-sm space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('declarations.store') }}" enctype="multipart/form-data" x-data="{ type: 'perte' }" class="space-y-6">
                    @csrf

                    {{-- Type de déclaration --}}
                    <div>
                        <x-input-label value="Type de déclaration" />
                        <div class="mt-2 grid grid-cols-2 gap-4">
                            <label class="flex items-center gap-2 border rounded-lg p-4 cursor-pointer transition"
                                   :class="type === 'perte' ? 'border-alerte ring-1 ring-alerte bg-alerte/5' : 'border-gray-300'">
                                <input type="radio" name="type" value="perte" x-model="type" class="text-alerte focus:ring-alerte">
                                <span>🔍 Déclarer une perte</span>
                            </label>
                            <label class="flex items-center gap-2 border rounded-lg p-4 cursor-pointer transition"
                                   :class="type === 'decouverte' ? 'border-sonar ring-1 ring-sonar bg-sonar/5' : 'border-gray-300'">
                                <input type="radio" name="type" value="decouverte" x-model="type" class="text-sonar focus:ring-sonar">
                                <span>📢 Déclarer une découverte</span>
                            </label>
                        </div>
                    </div>

                    {{-- Catégorie --}}
                    <div>
                        <x-input-label for="categorie" value="Catégorie" />
                        <select id="categorie" name="categorie" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte">
                            <option value="">-- Sélectionner --</option>
                            <option value="personne" {{ old('categorie') === 'personne' ? 'selected' : '' }}>Personne</option>
                            <option value="objet" {{ old('categorie') === 'objet' ? 'selected' : '' }}>Objet</option>
                        </select>
                    </div>

                    {{-- Type perte (si perte) --}}
                    <div x-show="type === 'perte'" x-cloak>
                        <x-input-label for="type_perte" value="Précision (ex: personne disparue, objet perdu...)" />
                        <x-text-input id="type_perte" name="type_perte" type="text" class="mt-1 block w-full"
                                      :value="old('type_perte')" />
                    </div>

                    {{-- Type découverte (si découverte) --}}
                    <div x-show="type === 'decouverte'" x-cloak>
                        <x-input-label for="type_decouverte" value="Précision (ex: personne trouvée, objet trouvé...)" />
                        <x-text-input id="type_decouverte" name="type_decouverte" type="text" class="mt-1 block w-full"
                                      :value="old('type_decouverte')" />
                    </div>

                    {{-- Description --}}
                    <div>
                        <x-input-label for="description" value="Description" />
                        <textarea id="description" name="description" rows="4" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte">{{ old('description') }}</textarea>
                    </div>

                    {{-- Lieu (résumé texte) --}}
                    <div>
                        <x-input-label for="lieu" value="Lieu (résumé)" />
                        <x-text-input id="lieu" name="lieu" type="text" class="mt-1 block w-full" :value="old('lieu')" />
                    </div>

                    {{-- Localisation --}}
                    <fieldset class="border border-gray-200 rounded-lg p-4">
                        <legend class="text-sm font-medium text-gray-700 px-2">📍 Localisation</legend>

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="adresse" value="Adresse" />
                                <x-text-input id="adresse" name="adresse" type="text" required
                                              class="mt-1 block w-full" :value="old('adresse')" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="latitude" value="Latitude (optionnel)" />
                                    <x-text-input id="latitude" name="latitude" type="number" step="any"
                                                  class="mt-1 block w-full" :value="old('latitude')" />
                                </div>
                                <div>
                                    <x-input-label for="longitude" value="Longitude (optionnel)" />
                                    <x-text-input id="longitude" name="longitude" type="number" step="any"
                                                  class="mt-1 block w-full" :value="old('longitude')" />
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Pièces jointes (multiple) --}}
                    <div>
                        <x-input-label for="pieces_jointes" value="Pièces jointes (photos, PDF — 5 max, 4 Mo chacune)" />
                        <input id="pieces_jointes" name="pieces_jointes[]" type="file" multiple
                               accept=".jpg,.jpeg,.png,.pdf"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-azur/10 file:text-azur hover:file:bg-azur/20" />
                        <p class="mt-1 text-xs text-gray-400">Formats acceptés : JPG, PNG, PDF.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <a href="{{ route('declarations.index') }}"
                           class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                            Annuler
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-alerte border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-alerte-dark focus:outline-none focus:ring-2 focus:ring-alerte focus:ring-offset-2 transition">
                            Soumettre la déclaration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>