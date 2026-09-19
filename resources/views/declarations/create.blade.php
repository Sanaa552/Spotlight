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
                          type: '{{ old('type', $defaultType) }}',
                          categorie: '{{ old('categorie', $defaultCategory) }}',
                          fileError: '',
                          validateFiles(event) {
                              const attachments = Array.from(this.$refs.attachments.files);
                              const publicPhoto = Array.from(this.$refs.publicPhoto.files);
                              const lossReport = this.type === 'perte'
                                  ? Array.from(this.$refs.lossReport.files)
                                  : [];
                              const discoveryVideo = this.type === 'decouverte' && this.categorie === 'objet'
                                  ? Array.from(this.$refs.discoveryVideo.files)
                                  : [];
                              const authorityProof = this.type === 'decouverte'
                                  ? Array.from(this.$refs.authorityProof.files)
                                  : [];
                              const files = [...attachments, ...lossReport, ...publicPhoto, ...discoveryVideo, ...authorityProof];
                              const oversized = files.find(file => file.size > (discoveryVideo.includes(file) ? 30 : 10) * 1024 * 1024);
                              const total = files.reduce((size, file) => size + file.size, 0);

                              this.fileError = attachments.length > 5
                                  ? 'Vous pouvez sélectionner au maximum 5 fichiers.'
                                  : oversized
                                      ? `Le fichier « ${oversized.name} » dépasse la taille autorisée.`
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
                        <select id="categorie" name="categorie" required x-model="categorie"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-alerte focus:ring-alerte">
                            <option value="">-- Sélectionner --</option>
                            <option value="personne" {{ old('categorie', $defaultCategory) === 'personne' ? 'selected' : '' }}>Personne</option>
                            <option value="objet" {{ old('categorie', $defaultCategory) === 'objet' ? 'selected' : '' }}>Objet</option>
                        </select>
                    </div>

                    <div x-show="type === 'perte' && categorie === 'personne'" x-cloak class="border-l-4 border-alerte bg-alerte/5 px-4 py-3 text-sm text-gray-700">
                        En cas de disparition d’une personne, contactez sans attendre la <a href="tel:117" class="font-semibold underline">Police au 117</a> ou la <a href="tel:113" class="font-semibold underline">Gendarmerie au 113</a>. Spotlight ne remplace pas ce signalement. Joignez le document remis par les autorités pour soumettre le dossier.
                    </div>

                    <div x-show="type === 'decouverte'" x-cloak class="border-l-4 border-sonar bg-sonar/5 px-4 py-3 text-sm text-gray-700">
                        <span x-show="categorie === 'personne'">Si une personne, notamment un enfant, est en danger, contactez immédiatement la <a href="tel:117" class="font-semibold underline">Police au 117</a> ou la <a href="tel:113" class="font-semibold underline">Gendarmerie au 113</a>. Ne la filmez pas et ne publiez pas sa position. Ce dossier restera privé.</span>
                        <span x-show="categorie !== 'personne'">Filmez l’objet et le lieu si cela ne présente aucun danger, puis remettez l’objet à un poste de police ou de gendarmerie. Vous pourrez ajouter le récépissé après avoir enregistré ce dossier privé.</span>
                        <a x-show="categorie === 'objet'" href="{{ route('commissariats.rechercher') }}" target="_blank" rel="noopener noreferrer"
                           class="mt-2 inline-flex items-center gap-2 font-semibold text-azur hover:underline">
                            <x-icon name="location" class="h-4 w-4" /> Trouver un poste à proximité
                        </a>
                    </div>

                    <div x-show="type === 'decouverte' && categorie === 'objet'" x-cloak>
                        <x-input-label for="poste_prevu" value="Poste envisagé (facultatif)" />
                        <x-text-input id="poste_prevu" name="poste_prevu" type="text" class="mt-1 block w-full"
                                      :value="old('poste_prevu', $posteInitial)" maxlength="255"
                                      x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'" />
                        <p id="poste-selection-status" role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Le choix d’un poste ne remplace pas le récépissé de remise ou de signalement.</p>
                        <x-input-error :messages="$errors->get('poste_prevu')" class="mt-2" />
                    </div>

                    <div x-show="type === 'decouverte' && categorie === 'objet'" x-cloak class="border-t border-gray-100 pt-5">
                        <label for="perte_id" class="block text-sm font-medium text-gray-700">Cet objet figure-t-il déjà parmi les pertes ?</label>
                        <input id="perte_id" name="perte_id" type="hidden" value="{{ old('perte_id', $perteInitiale?->id) }}"
                               x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'">
                        <div id="selected-loss" class="mt-3 {{ $perteInitiale ? '' : 'hidden' }} flex items-center gap-3 rounded-md border border-gray-200 p-3">
                            <img id="selected-loss-photo" src="{{ $perteInitiale?->photoUrl() }}" alt="Photo de la perte sélectionnée" class="h-16 w-16 shrink-0 rounded-md object-cover bg-gray-100">
                            <div class="min-w-0 flex-1">
                                <a id="selected-loss-link" href="{{ $perteInitiale ? route('public.declarations.show', $perteInitiale) : '#' }}" target="_blank" rel="noopener noreferrer"
                                   class="text-sm font-semibold text-azur hover:underline">{{ $perteInitiale?->type_perte }} {{ $perteInitiale ? '#'.$perteInitiale->id : '' }}</a>
                                <p class="text-xs text-gray-500">Correspondance à vérifier par la modération.</p>
                            </div>
                            <button id="clear-loss" type="button" title="Retirer la correspondance" class="shrink-0 text-lg text-gray-500 hover:text-gray-900" aria-label="Retirer la correspondance">&times;</button>
                        </div>
                        <a href="{{ route('rapprochements.pertes') }}" target="_blank" rel="noopener noreferrer"
                           class="mt-3 inline-block text-sm font-semibold text-azur hover:underline">Chercher parmi les pertes d’objets</a>
                        <p class="mt-1 text-xs text-gray-500">Si aucune annonce ne correspond, laissez ce champ vide : votre découverte sera enregistrée normalement.</p>
                        <x-input-error :messages="$errors->get('perte_id')" class="mt-2" />
                    </div>

                    {{-- Type perte (si perte) --}}
                    <div x-show="type === 'perte'" x-cloak>
                        <x-input-label for="type_perte" value="Précision (ex: personne disparue, objet perdu...)" />
                        <x-text-input id="type_perte" name="type_perte" type="text" class="mt-1 block w-full"
                                      :value="old('type_perte')" />
                    </div>

                    <div x-show="type === 'perte'" x-cloak>
                        <x-input-label for="declaration_perte" value="Preuve du signalement aux autorités" />
                        <input id="declaration_perte" name="declaration_perte" type="file"
                               accept=".jpg,.jpeg,.png,.pdf"
                               x-ref="lossReport"
                               x-bind:disabled="type !== 'perte'"
                               x-bind:required="type === 'perte'"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-alerte/10 file:text-alerte-dark hover:file:bg-alerte/20" />
                        <p class="mt-1 text-xs text-gray-500">
                            Récépissé, procès-verbal ou autre document remis par les autorités. Pour un objet, attestation de perte si applicable. 10 Mo maximum. Document privé.
                        </p>
                    </div>

                    {{-- Type découverte (si découverte) --}}
                    <div x-show="type === 'decouverte'" x-cloak>
                        <x-input-label for="type_decouverte" value="Précision (ex: personne trouvée, objet trouvé...)" />
                        <x-text-input id="type_decouverte" name="type_decouverte" type="text" class="mt-1 block w-full"
                                      :value="old('type_decouverte')" />
                    </div>

                    <div x-show="type === 'decouverte' && categorie === 'objet'" x-cloak>
                        <x-input-label for="preuve_decouverte" value="Vidéo privée de l’objet et du lieu de découverte" />
                        <input id="preuve_decouverte" name="preuve_decouverte" type="file" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm"
                               x-ref="discoveryVideo" x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'"
                               x-bind:required="type === 'decouverte' && categorie === 'objet'"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600" />
                        <p class="mt-1 text-xs text-gray-500">MP4, MOV ou WebM, 30 Mo maximum. Ne filmez pas de personne identifiable. Cette vidéo reste privée.</p>
                    </div>

                    <div x-show="type === 'decouverte'" x-cloak>
                        <x-input-label for="preuve_signalement" value="Justificatif de remise ou de signalement aux autorités" />
                        <input id="preuve_signalement" name="preuve_signalement" type="file" accept=".jpg,.jpeg,.png,.pdf"
                               x-ref="authorityProof" x-bind:disabled="type !== 'decouverte'"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600" />
                        <p class="mt-1 text-xs text-gray-500">Récépissé ou autre preuve délivrée par le poste, JPG, PNG ou PDF, 10 Mo maximum. Vous pourrez l'ajouter plus tard ; aucune validation ou publication n'est possible avant.</p>
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
                        <p class="mt-1 text-xs text-gray-500">Quartier et ville uniquement : ce texte pourra être publié. Ne saisissez pas d'adresse privée.</p>
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

                    <div x-show="!(type === 'decouverte' && categorie === 'personne')" x-cloak>
                        <x-input-label for="photo_publique" value="Photo publique de la personne ou de l’objet" />
                        <input id="photo_publique" name="photo_publique" type="file"
                               accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                               x-ref="publicPhoto"
                               x-bind:disabled="type === 'decouverte' && categorie === 'personne'"
                               x-bind:required="!(type === 'decouverte' && categorie === 'personne')"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-sonar/10 file:text-sonar-dark hover:file:bg-sonar/20" />
                        <p class="mt-1 text-xs text-gray-500">JPG, JPEG ou PNG, 10 Mo maximum. Cette photo sera visible dans Spotlight et utilisée pour Facebook et Instagram.</p>
                    </div>

                    <div>
                        <x-input-label for="pieces_jointes" value="Justificatifs privés (5 max, 10 Mo chacun)" />
                        <input id="pieces_jointes" name="pieces_jointes[]" type="file" multiple
                               accept=".jpg,.jpeg,.png,.pdf"
                               x-ref="attachments"
                               x-on:change="validateFiles($event)"
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-azur/10 file:text-azur hover:file:bg-azur/20" />
                        <p class="mt-1 text-xs text-gray-500">Autres justificatifs : JPG, JPEG, PNG ou PDF. Visibles uniquement par vous et la modération. Taille totale maximale, vidéo comprise : 60 Mo.</p>
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
    <script>
        const chosenLoss = document.getElementById('perte_id');
        const lossPreview = document.getElementById('selected-loss');
        document.getElementById('clear-loss')?.addEventListener('click', () => {
            chosenLoss.value = '';
            lossPreview.classList.add('hidden');
        });
        if ('BroadcastChannel' in window) {
            const lossChannel = new BroadcastChannel('spotlight-pertes');
            lossChannel.addEventListener('message', event => {
                const selected = event.data;
                if (selected?.type !== 'perte-choisie' || !Number.isSafeInteger(selected.id) || chosenLoss.disabled) return;
                const url = new URL(selected.url, window.location.origin);
                if (url.origin !== window.location.origin || !url.pathname.startsWith('/declarations-publiques/')) return;
                chosenLoss.value = selected.id;
                document.getElementById('selected-loss-photo').src = selected.photo;
                const link = document.getElementById('selected-loss-link');
                link.href = url.href;
                link.textContent = `${selected.title} #${selected.id}`;
                lossPreview.classList.remove('hidden');
            });
        }
        function appliquerPoste(nom) {
            if (typeof nom !== 'string') return;
            const input = document.getElementById('poste_prevu');
            if (!input || input.disabled) return;
            input.value = nom.slice(0, 255);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            document.getElementById('poste-selection-status').textContent = 'Poste sélectionné. Confirmez son adresse avant de vous déplacer ; le récépissé reste obligatoire.';
        }
        if ('BroadcastChannel' in window) {
            const stationChannel = new BroadcastChannel('spotlight-postes');
            stationChannel.addEventListener('message', event => {
                if (event.data?.type === 'poste-choisi') appliquerPoste(event.data.nom);
            });
        }
        window.addEventListener('storage', event => {
            if (event.key !== 'spotlight-poste-choisi' || !event.newValue) return;
            try { appliquerPoste(JSON.parse(event.newValue).nom); } catch (_) {}
        });
    </script>
</x-app-layout>
