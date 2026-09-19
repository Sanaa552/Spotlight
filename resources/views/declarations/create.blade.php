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
                        <button x-show="categorie === 'objet'" type="button" x-on:click="$dispatch('open-modal', 'station-picker')"
                                class="mt-2 inline-flex items-center gap-2 font-semibold text-azur hover:underline">
                            <x-icon name="location" class="h-4 w-4" /> Choisir un poste où remettre l’objet
                        </button>
                    </div>

                    <div x-show="type === 'decouverte' && categorie === 'objet'" x-cloak>
                        <x-input-label for="poste_prevu" value="Poste où vous prévoyez de remettre l’objet (facultatif)" />
                        <x-text-input id="poste_prevu" name="poste_prevu" type="text" class="mt-1 block w-full"
                                      :value="old('poste_prevu', $posteInitial)" maxlength="255"
                                      x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'" />
                        <input id="poste_latitude" name="poste_latitude" type="hidden" value="{{ old('poste_latitude') }}" x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'">
                        <input id="poste_longitude" name="poste_longitude" type="hidden" value="{{ old('poste_longitude') }}" x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'">
                        <p id="poste-selection-status" role="status" aria-live="polite" class="mt-1 text-xs text-gray-500">Ce poste est distinct du lieu de découverte. Son choix ne remplace pas le récépissé des autorités.</p>
                        <x-input-error :messages="$errors->get('poste_prevu')" class="mt-2" />
                    </div>

                    <div x-show="type === 'decouverte' && categorie === 'objet'" x-cloak class="border-t border-gray-100 pt-5">
                        <label for="perte_id" class="block text-sm font-medium text-gray-700">L’objet a-t-il déjà été déclaré perdu sur Spotlight ?</label>
                        <input id="perte_id" name="perte_id" type="hidden" value="{{ old('perte_id', $perteInitiale?->id) }}"
                               x-bind:disabled="type !== 'decouverte' || categorie !== 'objet'">
                        <p id="selected-loss-empty" class="mt-2 text-sm text-gray-500 {{ $perteInitiale ? 'hidden' : '' }}">Aucune annonce choisie. Vous pouvez continuer et déclarer cet objet même s’il n’est pas sur Spotlight.</p>
                        <div id="selected-loss" class="mt-3 {{ $perteInitiale ? '' : 'hidden' }} flex items-center gap-3 rounded-md border border-gray-200 p-3">
                            <img id="selected-loss-photo" src="{{ $perteInitiale?->photoUrl() }}" alt="Photo de la perte sélectionnée" class="h-16 w-16 shrink-0 rounded-md object-cover bg-gray-100">
                            <div class="min-w-0 flex-1">
                                <a id="selected-loss-link" href="{{ $perteInitiale ? route('public.declarations.show', $perteInitiale) : '#' }}" target="_blank" rel="noopener noreferrer"
                                   class="text-sm font-semibold text-azur hover:underline">{{ $perteInitiale?->type_perte }} {{ $perteInitiale ? '#'.$perteInitiale->id : '' }}</a>
                                <p class="text-xs text-gray-500">Correspondance à vérifier par la modération.</p>
                            </div>
                            <button id="clear-loss" type="button" title="Retirer la correspondance" class="shrink-0 text-lg text-gray-500 hover:text-gray-900" aria-label="Retirer la correspondance">&times;</button>
                        </div>
                        <button type="button" x-on:click="$dispatch('open-modal', 'loss-picker')"
                                class="mt-3 text-sm font-semibold text-azur hover:underline">Voir les objets perdus et choisir une annonce</button>
                        <p class="mt-1 text-xs text-gray-500">La correspondance sera proposée à la modération ; elle ne sera pas validée automatiquement.</p>
                        <x-input-error :messages="$errors->get('perte_id')" class="mt-2" />
                    </div>

                    {{-- Type perte (si perte) --}}
                    <div x-show="type === 'perte'" x-cloak>
                        <x-input-label for="type_perte" value="Quel objet ou quelle personne recherchez-vous ?" />
                        <x-text-input id="type_perte" name="type_perte" type="text" class="mt-1 block w-full"
                                      :value="old('type_perte')" placeholder="Ex. portefeuille noir, enfant disparu" />
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
                        <x-input-label for="type_decouverte" value="Qu’avez-vous trouvé ou signalé ?" />
                        <x-text-input id="type_decouverte" name="type_decouverte" type="text" class="mt-1 block w-full"
                                      :value="old('type_decouverte')" placeholder="Ex. trousseau de clés, personne à protéger" />
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
                        <x-input-label for="lieu" value="Quartier et ville de la perte ou de la découverte" />
                        <x-text-input id="lieu" name="lieu" type="text" class="mt-1 block w-full" :value="old('lieu')" />
                        <p class="mt-1 text-xs text-gray-500">Quartier et ville uniquement : ce texte pourra être publié. Ne saisissez pas d'adresse privée.</p>
                    </div>

                    {{-- Localisation --}}
                    <fieldset class="border border-gray-200 rounded-lg p-4">
                        <legend class="text-sm font-medium text-gray-700 px-2"> Lieu de la perte ou de la découverte</legend>

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="adresse" value="Adresse ou repère du lieu de l’événement" />
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
    <x-modal name="station-picker" maxWidth="2xl" focusable>
        <div x-data="stationPicker()" class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Choisir un poste pour la remise</h2>
                    <p class="mt-1 text-sm text-gray-600">Choisissez d’abord la ville. Le poste reste à confirmer avant déplacement.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" aria-label="Fermer" class="text-xl text-gray-500 hover:text-gray-900">&times;</button>
            </div>
            <div class="mt-5 flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="station-city" class="block text-sm font-medium text-gray-700">Ville</label>
                    <select id="station-city" x-model="city" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        <option value="">Choisir une ville</option>
                        @foreach ($villes as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </div>
                <button type="button" x-on:click="search()" x-bind:disabled="loading" class="rounded-md bg-azur px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Rechercher</button>
                <button type="button" x-on:click="locate()" x-bind:disabled="loading" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50">Autoriser ma position</button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Le navigateur vous demandera l’autorisation après ce clic. Votre position sert uniquement au tri sur cet appareil ; elle n’est pas envoyée à Spotlight ou à OpenStreetMap. Le quartier du poste est recherché lorsque vous le choisissez.</p>
            <p x-show="message" x-text="message" role="status" aria-live="polite" class="mt-3 text-sm text-gray-700"></p>
            <div class="mt-4 max-h-72 divide-y divide-gray-200 overflow-y-auto border-y border-gray-200">
                <template x-for="(station, index) in stations" :key="`${station.lat}-${station.lng}-${index}`">
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900" x-text="station.nom"></p>
                            <p class="text-xs text-gray-600" x-text="station.adresse || city"></p>
                            <p class="text-xs text-gray-500" x-text="station.distanceLabel || `${station.distance} km du centre-ville (à vol d’oiseau)`"></p>
                        </div>
                        <button type="button" x-on:click="preview = station" class="text-xs font-medium text-azur underline">Voir sur carte</button>
                        <button type="button" x-on:click="choose(station)" x-bind:disabled="choosing" class="rounded-md bg-sonar px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Choisir ce poste</button>
                    </div>
                </template>
            </div>
            <template x-if="preview">
                <div class="mt-4">
                    <p class="mb-2 text-sm font-medium text-gray-900" x-text="preview.nom"></p>
                    <iframe title="Emplacement du poste choisi" x-bind:src="mapUrl(preview)" loading="lazy" class="h-48 w-full border border-gray-200"></iframe>
                    <p class="mt-1 text-xs text-gray-500">Point référencé sur OpenStreetMap ; vérifiez l’adresse auprès du poste.</p>
                </div>
            </template>
        </div>
    </x-modal>

    <x-modal name="loss-picker" maxWidth="2xl" focusable>
        <div x-data="lossPicker()" x-on:open-modal.window="if ($event.detail === 'loss-picker') search(1)" class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Objets déclarés perdus</h2>
                    <p class="mt-1 text-sm text-gray-600">Comparez la photo et la description. Vous pouvez aussi déclarer sans correspondance.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" aria-label="Fermer" class="text-xl text-gray-500 hover:text-gray-900">&times;</button>
            </div>
            <div class="mt-5 flex gap-2">
                <label for="loss-search" class="sr-only">Chercher un objet</label>
                <input id="loss-search" x-model="query" x-on:keydown.enter.prevent="search(1)" maxlength="80" placeholder="Objet, description ou quartier" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                <button type="button" x-on:click="search(1)" class="rounded-md bg-azur px-4 py-2 text-sm font-semibold text-white">Chercher</button>
            </div>
            <p x-show="message" x-text="message" role="status" aria-live="polite" class="mt-3 text-sm text-gray-700"></p>
            <div class="mt-4 max-h-96 divide-y divide-gray-200 overflow-y-auto border-y border-gray-200">
                <template x-for="loss in losses" :key="loss.id">
                    <div class="flex gap-3 py-3">
                        <img x-bind:src="loss.photo" alt="Photo de l’objet perdu" class="h-20 w-20 shrink-0 rounded-md object-cover bg-gray-100">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900"><span x-text="loss.titre"></span> <span class="text-gray-500" x-text="`#${loss.id}`"></span></p>
                            <p class="mt-1 text-xs text-gray-500" x-text="loss.lieu || 'Lieu non précisé'"></p>
                            <p class="mt-1 line-clamp-2 text-xs text-gray-700" x-text="loss.description"></p>
                            <button type="button" x-on:click="choose(loss)" class="mt-2 text-xs font-semibold text-sonar-dark underline">Choisir cette annonce</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3">
                <button type="button" x-on:click="clear()" class="text-sm font-medium text-gray-600 underline">Aucune annonce ne correspond</button>
                <button x-show="page < lastPage" type="button" x-on:click="search(page + 1)" class="text-sm font-medium text-azur underline">Voir la suite</button>
            </div>
        </div>
    </x-modal>
    <script>
        window.stationPicker = () => ({
            city: '', stations: [], preview: null, loading: false, choosing: false, message: '', location: null,
            async search() {
                if (!this.city) { this.message = 'Choisissez une ville pour afficher les postes.'; return; }
                this.loading = true;
                this.message = 'Recherche des postes…';
                this.preview = null;
                try {
                    const url = new URL(@json(route('commissariats.rechercher')));
                    url.searchParams.set('ville', this.city);
                    url.searchParams.set('format', 'json');
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) throw new Error('La recherche a échoué. Réessayez.');
                    const data = await response.json();
                    this.stations = data.postes || [];
                    if (this.location) this.sortStations();
                    this.message = data.erreur || (this.stations.length
                        ? `${this.stations.length} postes référencés${this.location ? ', triés depuis votre position' : ''}. Distances à vol d’oiseau.`
                        : 'Aucun poste référencé dans cette ville. Contactez directement les autorités.');
                } catch (error) {
                    this.stations = [];
                    this.message = error.message || 'Recherche indisponible. Contactez directement les autorités.';
                } finally { this.loading = false; }
            },
            locate() {
                if (!window.isSecureContext || !navigator.geolocation) {
                    this.message = 'La position nécessite HTTPS et un navigateur compatible. La recherche par ville reste disponible.';
                    return;
                }
                this.message = 'Recherche de votre position…';
                navigator.geolocation.getCurrentPosition(position => {
                    this.location = { lat: position.coords.latitude, lng: position.coords.longitude };
                    if (this.stations.length) {
                        this.sortStations();
                        this.message = 'Position autorisée. Postes triés par proximité ; votre position reste dans le navigateur.';
                    } else {
                        this.message = 'Position autorisée. Choisissez maintenant une ville et recherchez les postes.';
                    }
                }, error => {
                    this.message = error.code === 1
                        ? 'Accès refusé. Dans le navigateur, ouvrez les informations du site à gauche de l’adresse et autorisez « Position ». Vérifiez aussi la localisation dans les paramètres Windows, puis réessayez.'
                        : error.code === 3 ? 'La recherche de position a expiré. Réessayez ou utilisez la recherche par ville.'
                            : 'Position indisponible. Utilisez la recherche par ville.';
                }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 });
            },
            sortStations() {
                const lat = this.location.lat * Math.PI / 180;
                const lng = this.location.lng * Math.PI / 180;
                this.stations = this.stations.map(station => {
                    const stationLat = Number(station.lat) * Math.PI / 180;
                    const stationLng = Number(station.lng) * Math.PI / 180;
                    const value = Math.sin((stationLat - lat) / 2) ** 2 + Math.cos(lat) * Math.cos(stationLat) * Math.sin((stationLng - lng) / 2) ** 2;
                    const distance = 12742 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
                    return { ...station, distanceFromUser: distance, distanceLabel: `${distance.toFixed(2)} km de votre position (à vol d’oiseau)` };
                }).sort((a, b) => a.distanceFromUser - b.distanceFromUser);
            },
            mapUrl(station) {
                const lat = Number(station.lat), lng = Number(station.lng);
                const box = [lng - 0.008, lat - 0.008, lng + 0.008, lat + 0.008].join(',');
                return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(box)}&layer=mapnik&marker=${lat}%2C${lng}`;
            },
            async choose(station) {
                if (this.choosing) return;
                this.choosing = true;
                this.message = 'Recherche du quartier de ce poste…';
                let quartier = null;
                let rue = null;
                if (station.osm_id) {
                    try {
                        const url = new URL(@json(route('commissariats.adresse')));
                        url.searchParams.set('ville', this.city);
                        url.searchParams.set('osm_id', station.osm_id);
                        let response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (response.status === 503) {
                            await new Promise(resolve => setTimeout(resolve, 1100));
                            response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        }
                        if (!response.ok) throw new Error('Adresse indisponible');
                        const data = await response.json();
                        quartier = data.quartier;
                        rue = data.rue;
                    } catch (_) {
                        this.message = 'Quartier non disponible. Vérifiez le poste sur la carte avant de vous déplacer.';
                    }
                }
                const localisation = [quartier, rue, this.city].filter(Boolean).join(', ');
                const adresse = quartier || rue ? localisation : `quartier non renseigné, ${station.adresse || this.city}`;
                window.dispatchEvent(new CustomEvent('station-picked', { detail: { ...station, adresse, ville: this.city } }));
                this.choosing = false;
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'station-picker' }));
            },
        });
        window.lossPicker = () => ({
            query: '', losses: [], page: 1, lastPage: 1, message: '',
            async search(page = 1) {
                this.message = 'Recherche des annonces…';
                try {
                    const url = new URL(@json(route('rapprochements.pertes')));
                    url.searchParams.set('format', 'json');
                    url.searchParams.set('q', this.query);
                    url.searchParams.set('page', page);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) throw new Error('Recherche indisponible. Réessayez.');
                    const data = await response.json();
                    this.losses = data.pertes || [];
                    this.page = data.page;
                    this.lastPage = data.derniere_page;
                    this.message = this.losses.length ? '' : 'Aucune annonce correspondante. Continuez sans sélectionner de perte.';
                } catch (error) {
                    this.losses = [];
                    this.message = error.message || 'Recherche indisponible. Réessayez.';
                }
            },
            choose(loss) {
                window.dispatchEvent(new CustomEvent('loss-picked', { detail: loss }));
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'loss-picker' }));
            },
            clear() {
                window.dispatchEvent(new CustomEvent('loss-cleared'));
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'loss-picker' }));
            },
        });
        const chosenLoss = document.getElementById('perte_id');
        const lossPreview = document.getElementById('selected-loss');
        function effacerPerte() {
            chosenLoss.value = '';
            lossPreview.classList.add('hidden');
            document.getElementById('selected-loss-empty').classList.remove('hidden');
        }
        function appliquerPerte(selected) {
            if (!selected || !Number.isSafeInteger(selected.id) || chosenLoss.disabled) return;
            const url = new URL(selected.url, window.location.origin);
            if (url.origin !== window.location.origin || !url.pathname.startsWith('/declarations-publiques/')) return;
            chosenLoss.value = selected.id;
            document.getElementById('selected-loss-photo').src = selected.photo;
            const link = document.getElementById('selected-loss-link');
            link.href = url.href;
            link.textContent = `${selected.title || selected.titre} #${selected.id}`;
            lossPreview.classList.remove('hidden');
            document.getElementById('selected-loss-empty').classList.add('hidden');
        }
        document.getElementById('clear-loss')?.addEventListener('click', effacerPerte);
        window.addEventListener('loss-cleared', effacerPerte);
        window.addEventListener('loss-picked', event => appliquerPerte(event.detail));
        if ('BroadcastChannel' in window) {
            const lossChannel = new BroadcastChannel('spotlight-pertes');
            lossChannel.addEventListener('message', event => {
                if (event.data?.type === 'perte-choisie') appliquerPerte(event.data);
            });
        }
        function appliquerPoste(nom, lat = null, lng = null) {
            if (typeof nom !== 'string') return;
            const input = document.getElementById('poste_prevu');
            if (!input || input.disabled) return;
            input.value = nom.slice(0, 255);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            document.getElementById('poste_latitude').value = lat ?? '';
            document.getElementById('poste_longitude').value = lng ?? '';
            document.getElementById('poste-selection-status').textContent = `${nom} sélectionné. Le modérateur pourra voir son emplacement. Le récépissé reste obligatoire.`;
        }
        document.getElementById('poste_prevu')?.addEventListener('input', event => {
            if (!event.isTrusted) return;
            document.getElementById('poste_latitude').value = '';
            document.getElementById('poste_longitude').value = '';
            document.getElementById('poste-selection-status').textContent = 'Poste saisi manuellement : son emplacement n’est pas vérifié. Le récépissé reste obligatoire.';
        });
        window.addEventListener('station-picked', event => {
            const station = event.detail;
            appliquerPoste(`${station.nom} (${station.adresse || station.ville})`, station.lat, station.lng);
        });
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
