<?php

namespace App\Http\Controllers;

use App\Models\Declaration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CommissariatController extends Controller
{
    public const VILLES = [
        'Bafoussam', 'Bamenda', 'Bertoua', 'Buea', 'Douala', 'Dschang',
        'Ebolowa', 'Garoua', 'Kribi', 'Kumba', 'Limbe', 'Maroua',
        'Ngaoundéré', 'Yaoundé',
    ];

    public function rechercher(Request $request): View|JsonResponse
    {
        $ville = $request->validate(['ville' => ['nullable', 'string', Rule::in(self::VILLES)]])['ville'] ?? null;

        $vue = $this->afficherCarte($ville);

        if ($request->query('format') === 'json') {
            $donnees = $vue->getData();

            return response()->json([
                'ville' => $donnees['ville'],
                'lat' => $donnees['lat'],
                'lng' => $donnees['lng'],
                'postes' => $donnees['commissariats'],
                'erreur' => $donnees['erreurCarte'],
            ]);
        }

        return $vue;
    }

    public function proches(Request $request, Declaration $declaration): View
    {
        abort_unless(
            $declaration->user_id === $request->user()->id
                && $declaration->type === 'decouverte'
                && $declaration->categorie === 'objet',
            403
        );

        $ville = $request->validate(['ville' => ['nullable', 'string', Rule::in(self::VILLES)]])['ville'] ?? null;

        return $this->afficherCarte($ville, $declaration);
    }

    private function afficherCarte(?string $ville, ?Declaration $declaration = null): View
    {
        $lat = null;
        $lng = null;
        $commissariats = [];
        $erreurCarte = null;

        if ($ville !== null) {
            try {
                $resultats = Cache::remember('osm:ville:'.sha1($ville), now()->addDay(), function () use ($ville) {
                    $result = RateLimiter::attempt('osm:nominatim:spotlight', 1, function () use ($ville) {
                        return Http::withOptions(['verify' => config('services.openstreetmap.ca_bundle') ?: true])
                            ->withHeaders(['User-Agent' => 'Spotlight-Cameroon/1.0'])
                            ->timeout(8)
                            ->get('https://nominatim.openstreetmap.org/search', [
                                'q' => $ville.', Cameroun',
                                'format' => 'json',
                                'limit' => 1,
                            ])->throw()->json();
                    }, 1);

                    if ($result === false) {
                        throw new RuntimeException('Limite de recherche OpenStreetMap atteinte.');
                    }

                    return $result;
                });

                if (! is_numeric($resultats[0]['lat'] ?? null) || ! is_numeric($resultats[0]['lon'] ?? null)) {
                    $erreurCarte = 'Cette ville n’a pas été trouvée sur la carte. Contactez directement les autorités.';
                } else {
                    $lat = (float) $resultats[0]['lat'];
                    $lng = (float) $resultats[0]['lon'];
                }
            } catch (Throwable $exception) {
                Log::warning('Recherche de ville indisponible Spotlight', [
                    'declaration_id' => $declaration?->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $erreurCarte = 'La carte est momentanément indisponible. Contactez directement la police ou la gendarmerie.';
            }

            if ($lat !== null && $lng !== null) {
                try {
                    $elements = Cache::remember('osm:postes:v2:'.sha1($lat.','.$lng), now()->addHours(6), function () use ($lat, $lng) {
                        $query = "[out:json][timeout:15]; nwr[\"amenity\"=\"police\"](around:25000,{$lat},{$lng}); out center;";

                        return Http::withOptions(['verify' => config('services.openstreetmap.ca_bundle') ?: true])
                            ->withHeaders(['User-Agent' => 'Spotlight-Cameroon/1.0'])
                            ->timeout(20)
                            ->asForm()
                            ->post('https://overpass-api.de/api/interpreter', ['data' => $query])
                            ->throw()->json('elements', []);
                    });

                    $commissariats = collect($elements)->map(function ($element) use ($lat, $lng, $ville) {
                        $posteLat = $element['lat'] ?? $element['center']['lat'] ?? null;
                        $posteLng = $element['lon'] ?? $element['center']['lon'] ?? null;

                        if (! is_numeric($posteLat) || ! is_numeric($posteLng)) {
                            return null;
                        }

                        $posteLat = (float) $posteLat;
                        $posteLng = (float) $posteLng;
                        $tags = $element['tags'] ?? [];
                        $adresse = trim(implode(', ', array_filter([
                            $tags['addr:housenumber'] ?? null,
                            $tags['addr:street'] ?? $tags['addr:full'] ?? null,
                            $tags['addr:suburb'] ?? $tags['addr:city'] ?? $ville,
                        ])));

                        return [
                            'nom' => $tags['name'] ?? 'Poste de police ou de gendarmerie',
                            'adresse' => $adresse,
                            'lat' => $posteLat,
                            'lng' => $posteLng,
                            'distance' => round($this->distanceKm($lat, $lng, $posteLat, $posteLng), 2),
                        ];
                    })->filter()->sortBy('distance')->values()->take(100)->all();
                } catch (Throwable $exception) {
                    Log::warning('Recherche de postes indisponible Spotlight', [
                        'declaration_id' => $declaration?->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                    $erreurCarte = 'La liste des postes est momentanément indisponible. Contactez directement les autorités.';
                }
            }
        }

        return view('declarations.commissariats', [
            'declaration' => $declaration,
            'ville' => $ville,
            'villes' => self::VILLES,
            'lat' => $lat,
            'lng' => $lng,
            'commissariats' => $commissariats,
            'erreurCarte' => $erreurCarte,
        ]);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
