<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-argent leading-tight">
                Commissariats à proximité
            </h2>
            <a href="{{ route('declarations.show', $declaration) }}" class="text-sm text-alerte hover:underline">
                ← Retour à la déclaration
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-4">
                <p class="text-sm text-gray-600">
                    📍 Objet/personne signalé(e) : <strong>{{ $declaration->localisation->adresse }}</strong>
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    Dépose l'objet trouvé (ou signale la personne) au commissariat ou à la brigade de gendarmerie le plus proche.
                </p>
            </div>

            @if (! $lat || ! $lng)
                <div class="bg-alerte/10 border border-alerte/30 text-alerte-dark px-4 py-3 rounded-lg text-sm">
                    Impossible de localiser cette adresse automatiquement. Contacte directement
                    la Police (117) ou la Gendarmerie (113) la plus proche de chez toi.
                </div>
            @else
                {{-- Carte --}}
                <div id="map" class="w-full h-96 rounded-lg shadow-sm"></div>

                {{-- Liste des commissariats --}}
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-900 text-sm">
                            {{ count($commissariats) }} commissariat(s)/brigade(s) trouvé(s) dans un rayon de 5 km
                        </h3>
                    </div>

                    @if (empty($commissariats))
                        <div class="p-6 text-center text-sm text-gray-500">
                            Aucun poste référencé dans OpenStreetMap à proximité. Contacte la
                            Police (117) ou la Gendarmerie (113) pour connaître le poste le plus proche.
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($commissariats as $i => $c)
                                <li class="px-4 py-3 flex items-center justify-between hover:bg-gray-50 cursor-pointer commissariat-item"
                                    data-lat="{{ $c['lat'] }}" data-lng="{{ $c['lng'] }}" data-index="{{ $i }}">
                                    <div class="flex items-center gap-3">
                                        <span class="w-7 h-7 rounded-full bg-alerte/10 text-alerte text-xs font-bold flex items-center justify-center shrink-0">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="text-sm text-gray-800">{{ $c['nom'] }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $c['distance'] }} km</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

        </div>
    </div>

    @if ($lat && $lng)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const map = L.map('map').setView([{{ $lat }}, {{ $lng }}], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(map);

            // Marqueur du lieu de la déclaration
            L.marker([{{ $lat }}, {{ $lng }}], {
                icon: L.divIcon({
                    className: '',
                    html: '<div style="background:#E31E24;width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 0 4px rgba(0,0,0,0.4);"></div>',
                    iconSize: [16, 16],
                })
            }).addTo(map).bindPopup('📍 Lieu de la déclaration').openPopup();

            // Marqueurs des commissariats
            const commissariats = @json($commissariats);
            const markers = [];

            commissariats.forEach((c, i) => {
                const marker = L.marker([c.lat, c.lng], {
                    icon: L.divIcon({
                        className: '',
                        html: '<div style="background:#12579B;color:white;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;border:2px solid white;box-shadow:0 0 4px rgba(0,0,0,0.4);">' + (i + 1) + '</div>',
                        iconSize: [22, 22],
                    })
                }).addTo(map).bindPopup(`<strong>${c.nom}</strong><br>${c.distance} km`);
                markers.push(marker);
            });

            // Clic sur un élément de la liste -> centre la carte + ouvre le popup
            document.querySelectorAll('.commissariat-item').forEach(item => {
                item.addEventListener('click', () => {
                    const index = parseInt(item.dataset.index);
                    const lat = parseFloat(item.dataset.lat);
                    const lng = parseFloat(item.dataset.lng);
                    map.setView([lat, lng], 16);
                    markers[index].openPopup();
                });
            });
        </script>
    @endif
</x-app-layout>