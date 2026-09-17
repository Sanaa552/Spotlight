<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-argent leading-tight">
            {{ __('Nouvelle déclaration') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 md:p-8">

                @if ($errors->any())
                    <div class="mb-6 bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside text-sm space-y-1">
                            @foreach (array_unique($errors->all()) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('declarations.store') }}" enctype="multipart/form-data"
                      x-data="{
                          type: '{{ old('type', 'perte') }}',
                          fileError: '',
                          validateFiles(event) {
                              const attachments = Array.from(this.$refs.attachments.files);
                              const publicPhoto = Array.from(this.$refs.publicPhoto.files);
                              const lossReport = this.type === 'perte'
                                  ? Array.from(this.$refs.lossReport.files)
                                  : [];
                              const files = [...attachments, ...lossReport, ...publicPhoto];
                              const oversized = files.find(file => file.size > 10 * 1024 * 1024);
                              const total = files.reduce((size, file) => size + file.size, 0);

                              this.fileError = attachments.length > 5
                                  ? 'Vous pouvez sélectionner au maximum 5 fichiers.'
                                  : oversized
                                      ? `Le fichier « ${oversized.name} » dépasse la limite de 10 Mo.`
                                      : total > 60 * 1024 * 1024
                                          ? 'L’ensemble des fichiers ne doit pas dépasser 60 Mo.'
                                          : '';

                              if (this.fileError) {
                                  event.target.value = '';
                              }
                          }
                      }"
                      class="space-y-6">
                    @csrf

                    {{-- Type de déclaration --}}
                    <div>
                        <x-input-label value="Type de déclaration" />
                        <div class="mt-2 grid grid-cols-2 gap-4">
                            <label class="flex items-center gap-2 border rounded-lg p-4 cursor-pointer transition"
                                   :class="type === 'perte' ? 'border-alerte ring-1 ring-alerte bg-alerte/5' : 'border-gray-300'">
                                <input type="radio" name="type" value="perte" x-model="type" class="text-alerte focus:ring-alerte">
                                <span>Déclarer une perte</span>
                            </label>
                            <label class="flex items-center gap-2 border rounded-lg p-4 cursor-pointer transition"
                                   :class="type === 'decouverte' ? 'border-sonar ring-1 ring-sonar bg-sonar/5' : 'border-gray-300'">
                                <input type="radio" name="type" value="decouverte" x-model="type" class="text-sonar focus:ring-sonar">
                                <span> Déclarer une découverte</span>
                            </label>
                        </div>
                    </div>

                    {{-- Catégorie --}}
                    <div>
                        <x-input-label for="categorie" value="Personne ou objet concerné" />
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

                    <div x-show="type === 'perte'" x-cloak>
                        <x-input-label for="declaration_perte" value="Déclaration de perte officielle" />
                        <input id="declaration_perte" name="declaration_perte" type="file"
                               accept=".jpg,.jpeg,.png,.pdf"
                               x-ref="lossReport"
                               x-bind:disabled="type !== 'perte'"
                               x-bind:required="type === 'perte'"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-alerte/10 file:text-alerte-dark hover:file:bg-alerte/20" />
                        <p class="mt-1 text-xs text-gray-500">
                            Photo ou PDF du document officiel, pour une personne ou un objet perdu. 10 Mo maximum. Ce document reste privé.
                        </p>
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
                        <legend class="text-sm font-medium text-gray-700 px-2"> Localisation</legend>

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="adresse" value="Adresse" />
                                <x-text-input id="adresse" name="adresse" type="text" required
                                              class="mt-1 block w-full" :value="old('adresse')" />
                            </div>

                        </div>
                    </fieldset>

                    <div>
                        <x-input-label for="photo_publique" value="Photo publique de la personne ou de l’objet" />
                        <input id="photo_publique" name="photo_publique" type="file" required
                               accept=".jpg,.jpeg,image/jpeg"
                               x-ref="publicPhoto"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sonar/10 file:text-sonar-dark hover:file:bg-sonar/20" />
                        <p class="mt-1 text-xs text-gray-500">JPG ou JPEG, 10 Mo maximum. Cette photo sera visible dans Spotlight et utilisée pour Facebook et Instagram.</p>
                    </div>

                    <div>
                        <x-input-label for="pieces_jointes" value="Justificatifs privés (5 max, 10 Mo chacun)" />
                        <input id="pieces_jointes" name="pieces_jointes[]" type="file" multiple
                               accept=".jpg,.jpeg,.png,.pdf"
                               x-ref="attachments"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-azur/10 file:text-azur hover:file:bg-azur/20" />
                        <p class="mt-1 text-xs text-gray-500">CNI, passeport et autres preuves : JPG, JPEG, PNG ou PDF. Visibles uniquement par vous et la modération. Taille totale maximale : 60 Mo.</p>
                        <p x-show="fileError" x-text="fileError" class="mt-2 text-sm font-medium text-alerte" style="display:none"></p>
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
