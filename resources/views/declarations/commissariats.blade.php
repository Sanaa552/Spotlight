<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-argent">Postes à proximité</h2>
            <a href="{{ $declaration ? route('declarations.show', $declaration) : route('declarations.create') }}"
               class="text-sm text-alerte hover:underline">Retour</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
        <form method="GET" action="{{ $declaration ? route('declarations.commissariats', $declaration) : route('commissariats.rechercher') }}"
              class="flex flex-col gap-3 bg-white p-4 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="ville" class="block text-sm font-medium text-gray-800">Ville de recherche</label>
                <select id="ville" name="ville" required class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-azur focus:ring-azur">
                    <option value="">Choisir une ville</option>
                    @foreach ($villes as $option)
                        <option value="{{ $option }}" @selected($ville === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-azur px-5 py-2.5 text-sm font-semibold text-white hover:bg-azur-dark">Rechercher</button>
        </form>

        <p class="text-sm text-argent/70">
            La recherche utilise seulement la ville choisie, jamais l’adresse du dossier. Les postes référencés peuvent être incomplets : confirmez leur adresse avant de vous déplacer.
        </p>

        @if ($errors->any())
            <p role="alert" class="border-l-2 border-alerte pl-3 text-sm text-alerte">Sélectionnez une ville dans la liste.</p>
        @endif
        @if ($erreurCarte)
            <p role="status" class="border-l-2 border-laiton pl-3 text-sm text-argent">{{ $erreurCarte }}</p>
        @endif

        @if ($lat !== null && $lng !== null)
            <div id="map" class="h-72 w-full bg-white/5 sm:h-96" aria-label="Carte des postes référencés près de {{ $ville }}"></div>
            <section>
                <h3 class="text-base font-semibold text-argent">Postes référencés près de {{ $ville }}</h3>
                <p class="mt-1 text-xs text-argent/60">Rayon de 25 km autour du centre-ville. Distances à vol d’oiseau, pas un temps de trajet.</p>

                @if (empty($commissariats))
                    <p class="mt-4 text-sm text-argent/80">Aucun poste disponible dans cette recherche. Contactez directement les autorités.</p>
                @else
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button id="use-position" type="button" class="inline-flex items-center gap-2 rounded-md bg-azur px-4 py-2 text-sm font-semibold text-white hover:bg-azur-dark">
                            <x-icon name="location" class="h-4 w-4" /> Utiliser ma position
                        </button>
                        <span class="text-xs text-argent/70">Ou touchez un point sur la carte si elle s’affiche.</span>
                    </div>
                    <p class="mt-2 text-xs text-argent/60">Votre position reste dans votre navigateur : elle sert uniquement à trier les postes affichés.</p>
                    <p id="distance-info" role="status" aria-live="polite" class="mt-3 text-sm text-argent/80">Distances depuis le centre-ville.</p>
                    <ol id="stations-list" class="mt-4 divide-y divide-white/10 border-y border-white/10">
                        @foreach ($commissariats as $i => $poste)
                            <li class="station-row flex items-center justify-between gap-3 py-3" data-index="{{ $i }}" @if ($i >= 10) hidden @endif>
                                <button type="button" class="commissariat-item min-w-0 flex-1 text-left text-sm text-argent hover:text-white"
                                        data-index="{{ $i }}" data-lat="{{ $poste['lat'] }}" data-lng="{{ $poste['lng'] }}">
                                    <span class="station-rank mr-2 font-semibold text-azur">{{ $i + 1 }}.</span>{{ $poste['nom'] }}
                                    <span class="station-distance block pl-5 text-xs text-argent/60">{{ $poste['distance'] }} km du centre</span>
                                </button>
                                <a href="https://www.openstreetmap.org/?mlat={{ $poste['lat'] }}&mlon={{ $poste['lng'] }}#map=16/{{ $poste['lat'] }}/{{ $poste['lng'] }}"
                                   target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-medium text-azur hover:underline">Voir le poste</a>
                                @unless ($declaration)
                                    <button type="button" class="choose-station shrink-0 rounded-md border border-azur px-2 py-1 text-xs font-semibold text-azur hover:bg-azur hover:text-white"
                                            data-index="{{ $i }}">Choisir ce poste</button>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
            @unless ($declaration)
                <div id="station-selection" role="status" aria-live="polite" class="mt-4 hidden border-l-2 border-sonar pl-3 text-sm text-argent">
                    <span id="station-selection-message"></span>
                    <a id="continue-with-station" href="{{ route('declarations.create') }}" class="ml-2 font-semibold text-azur underline">Continuer la déclaration</a>
                </div>
            @endunless
        @elseif (! $ville)
            <p class="py-8 text-center text-sm text-argent/60">Choisissez une ville pour afficher les postes référencés.</p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-5 text-sm">
            <p class="text-argent/70">En cas de danger : <a href="tel:117" class="font-semibold text-argent underline">Police 117</a> ou <a href="tel:113" class="font-semibold text-argent underline">Gendarmerie 113</a>.</p>
            @unless ($declaration)
                <a href="{{ route('declarations.create') }}" class="font-semibold text-azur hover:underline">Revenir à la déclaration</a>
            @endunless
        </div>
    </div>

    @if ($lat !== null && $lng !== null)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const postes = @json($commissariats);
            const cityName = @json($ville);
            document.querySelectorAll('.choose-station').forEach(button => {
                button.addEventListener('click', () => {
                    const station = postes[Number(button.dataset.index)];
                    if (!station) return;
                    const name = `${station.nom} (${cityName})`.slice(0, 255);
                    if ('BroadcastChannel' in window) {
                        const channel = new BroadcastChannel('spotlight-postes');
                        channel.postMessage({ type: 'poste-choisi', nom: name });
                        channel.close();
                    }
                    try { localStorage.setItem('spotlight-poste-choisi', JSON.stringify({ nom: name, at: Date.now() })); } catch (_) {}
                    const selection = document.getElementById('station-selection');
                    document.getElementById('station-selection-message').textContent = `${name} sélectionné. Revenez au formulaire déjà ouvert ou continuez ici.`;
                    document.getElementById('continue-with-station').href = `{{ route('declarations.create') }}?poste=${encodeURIComponent(name)}`;
                    selection.classList.remove('hidden');
                });
            });

            const stationList = document.getElementById('stations-list');
            const distanceInfo = document.getElementById('distance-info');
            let map = null;
            let markers = [];
            let selectedPoint = null;

            if (typeof window.L !== 'undefined') {
                try {
                    map = L.map('map').setView([{{ $lat }}, {{ $lng }}], 12);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(map);
                    L.circle([{{ $lat }}, {{ $lng }}], {
                        radius: 900, color: '#148078', fillOpacity: 0.08, weight: 2,
                    }).addTo(map).bindPopup('Centre approximatif de la recherche');
                    markers = postes.map(poste => {
                        const marker = L.marker([poste.lat, poste.lng]).addTo(map);
                        const popup = document.createElement('strong');
                        popup.textContent = poste.nom;
                        marker.bindPopup(popup);
                        return marker;
                    });
                } catch (_) {
                    map = null;
                }
            }
            if (!map) {
                const mapArea = document.getElementById('map');
                mapArea.textContent = 'Fond de carte indisponible. La liste des postes reste utilisable.';
                mapArea.classList.add('grid', 'place-items-center', 'text-sm', 'text-argent');
            }

            document.querySelectorAll('.commissariat-item').forEach(item => {
                item.addEventListener('click', () => {
                    const index = Number(item.dataset.index);
                    if (map && markers[index]) {
                        map.setView([Number(item.dataset.lat), Number(item.dataset.lng)], 16);
                        markers[index].openPopup();
                    } else if (distanceInfo) {
                        distanceInfo.textContent = 'Le fond de carte est indisponible. Utilisez « Voir le poste » pour ouvrir OpenStreetMap.';
                    }
                });
            });

            function distanceMetres(a, b) {
                const rad = Math.PI / 180;
                const dLat = (b.lat - a.lat) * rad;
                const dLng = (b.lng - a.lng) * rad;
                const x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLng / 2) ** 2;
                return 12742000 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
            }

            function trierDepuis(point, origine) {
                if (!stationList) return;

                const reference = { lat: Number(point.lat), lng: Number(point.lng) };
                if (map) {
                    if (!selectedPoint) {
                        selectedPoint = L.circleMarker([reference.lat, reference.lng], {
                            radius: 8, color: '#E31E24', fillColor: '#E31E24', fillOpacity: 0.8,
                        }).addTo(map);
                    } else {
                        selectedPoint.setLatLng([reference.lat, reference.lng]);
                    }
                }

                Array.from(stationList.querySelectorAll('.station-row'))
                    .map(row => {
                        const poste = postes[Number(row.dataset.index)];
                        return { row, distance: distanceMetres(reference, poste) / 1000 };
                    })
                    .sort((a, b) => a.distance - b.distance)
                    .forEach(({ row, distance }, rang) => {
                        row.querySelector('.station-rank').textContent = `${rang + 1}.`;
                        row.querySelector('.station-distance').textContent = `${distance.toFixed(2)} km ${origine}`;
                        row.hidden = rang >= 10;
                        stationList.appendChild(row);
                    });

                const horsZone = distanceMetres(reference, { lat: {{ $lat }}, lng: {{ $lng }} }) > 25000;
                distanceInfo.textContent = horsZone
                    ? 'Position hors du secteur affiché : choisissez une ville plus proche pour obtenir les postes les plus proches.'
                    : `Postes classés par distance ${origine}. Votre position n’a pas été envoyée à Spotlight.`;
            }

            map?.on('click', event => trierDepuis(event.latlng, 'du point choisi'));
            document.getElementById('use-position')?.addEventListener('click', () => {
                if (!window.isSecureContext || !navigator.geolocation) {
                    distanceInfo.textContent = 'La position nécessite une page HTTPS et un navigateur compatible. Choisissez une ville ou un point sur la carte.';
                    return;
                }
                distanceInfo.textContent = 'Recherche de votre position…';
                navigator.geolocation.getCurrentPosition(
                    position => trierDepuis({ lat: position.coords.latitude, lng: position.coords.longitude }, 'de votre position'),
                    error => {
                        distanceInfo.textContent = error.code === 1
                            ? 'Accès à la position refusé. Autorisez la localisation pour ce site dans votre navigateur, puis réessayez. La recherche par ville reste disponible.'
                            : error.code === 3
                                ? 'La recherche de position a expiré. Réessayez ou utilisez la ville choisie.'
                                : 'Position indisponible sur cet appareil. Utilisez la ville choisie ou un point sur la carte.';
                    },
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 },
                );
            });
        </script>
    @endif
</x-app-layout>
