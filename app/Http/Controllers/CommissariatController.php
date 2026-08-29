<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CommissariatController extends Controller
{
    public function proches(Declaration $declaration): View
    {
        $localisation = $declaration->localisation;

        abort_if(! $localisation, 404, "Aucune localisation associée à cette déclaration.");

        $lat = $localisation->latitude;
        $lng = $localisation->longitude;

        // Si pas de coordonnées enregistrées, on géocode l'adresse via Nominatim
        if (! $lat || ! $lng) {
            $geo = Http::withHeaders(['User-Agent' => 'Spotlight-App/1.0'])
                ->withOptions(['verify' => false])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $localisation->adresse.', Cameroun',
                    'format' => 'json',
                    'limit' => 1,
                ])->json();

            if (! empty($geo)) {
                $lat = (float) $geo[0]['lat'];
                $lng = (float) $geo[0]['lon'];
            }
        }

        $commissariats = [];

        if ($lat && $lng) {
            // Overpass API : recherche des postes de police/gendarmerie dans un rayon de 5km
            $query = "[out:json][timeout:15];
                (
                  node[\"amenity\"=\"police\"](around:5000,{$lat},{$lng});
                  way[\"amenity\"=\"police\"](around:5000,{$lat},{$lng});
                );
                out center;";

            $response = Http::withHeaders(['User-Agent' => 'Spotlight-App/1.0'])
                ->timeout(20)
                ->asForm()
                ->withOptions(['verify' => false])
                ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);

            if ($response->successful()) {
                $elements = $response->json('elements', []);

                $commissariats = collect($elements)->map(function ($el) use ($lat, $lng) {
                    $elLat = $el['lat'] ?? $el['center']['lat'] ?? null;
                    $elLng = $el['lon'] ?? $el['center']['lon'] ?? null;

                    if (! $elLat || ! $elLng) {
                        return null;
                    }

                    return [
                        'nom' => $el['tags']['name'] ?? 'Poste de police / Gendarmerie',
                        'lat' => $elLat,
                        'lng' => $elLng,
                        'distance' => round($this->distanceKm($lat, $lng, $elLat, $elLng), 2),
                    ];
                })->filter()->sortBy('distance')->values()->take(10)->toArray();
            }
        }

        return view('declarations.commissariats', [
            'declaration' => $declaration,
            'lat' => $lat,
            'lng' => $lng,
            'commissariats' => $commissariats,
        ]);
    }

    /** Distance à vol d'oiseau en km (formule de Haversine) */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}