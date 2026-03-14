<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Données Satellitaires Environnementales
 * Sources : NASA (FIRMS, MODIS, POWER), ESA Copernicus (Sentinel Hub)
 */
class SatelliteService
{
    // ─────────────────────────────────────────────────────────────────
    // NASA FIRMS — Fire Information Resource Management System
    // Détection incendies actifs (72h) — Pas de clé requise
    // ─────────────────────────────────────────────────────────────────
    public function getIncendiesNASA(float $lat, float $lon, float $rayon = 100): array
    {
        $cacheKey = 'nasa_firms_' . md5("$lat,$lon,$rayon");
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($lat, $lon, $rayon) {
            try {
                $apiKey = config('services.nasa.key', 'DEMO_KEY');
                $bbox   = implode(',', [
                    round($lon - $rayon / 111.0, 4),
                    round($lat - $rayon / 111.0, 4),
                    round($lon + $rayon / 111.0, 4),
                    round($lat + $rayon / 111.0, 4),
                ]);

                $response = Http::timeout(15)->get('https://firms.modaps.eosdis.nasa.gov/api/area/csv/' . $apiKey . '/VIIRS_SNPP_NRT/' . $bbox . '/1');

                if ($response->successful()) {
                    $lignes  = explode("\n", trim($response->body()));
                    $entetes = str_getcsv(array_shift($lignes));
                    $incendies = [];
                    foreach ($lignes as $ligne) {
                        if (empty(trim($ligne))) continue;
                        $vals = str_getcsv($ligne);
                        if (count($vals) === count($entetes)) {
                            $incendies[] = array_combine($entetes, $vals);
                        }
                    }
                    return [
                        'source'       => 'NASA FIRMS — VIIRS SNPP NRT',
                        'latitude'     => $lat,
                        'longitude'    => $lon,
                        'rayon_km'     => $rayon,
                        'disponible'   => true,
                        'nb_incendies' => count($incendies),
                        'incendies'    => array_slice($incendies, 0, 20),
                        'note'         => 'Détection thermique active (72h) — résolution 375m',
                    ];
                }
                return $this->indisponible('NASA FIRMS', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('NASA FIRMS error: ' . $e->getMessage());
                return $this->indisponible('NASA FIRMS', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // NASA POWER — Données météo et énergie solaire
    // API : https://power.larc.nasa.gov
    // Pas de clé requise
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesMeteoNASA(float $lat, float $lon, ?string $debut = null, ?string $fin = null): array
    {
        $debut = $debut ?? now()->subDays(30)->format('Ymd');
        $fin   = $fin   ?? now()->format('Ymd');

        $cacheKey = 'nasa_power_' . md5("$lat,$lon,$debut,$fin");
        return Cache::remember($cacheKey, now()->addHours(12), function () use ($lat, $lon, $debut, $fin) {
            try {
                $response = Http::timeout(20)->get('https://power.larc.nasa.gov/api/temporal/daily/point', [
                    'parameters' => 'T2M,RH2M,PRECTOTCORR,WS2M,ALLSKY_SFC_SW_DWN',
                    'community'  => 'RE',
                    'longitude'  => $lon,
                    'latitude'   => $lat,
                    'start'      => $debut,
                    'end'        => $fin,
                    'format'     => 'JSON',
                ]);

                if ($response->successful()) {
                    $props = $response->json('properties.parameter') ?? [];
                    return [
                        'source'      => 'NASA POWER — Prediction of Worldwide Energy Resources',
                        'latitude'    => $lat,
                        'longitude'   => $lon,
                        'periode'     => ['debut' => $debut, 'fin' => $fin],
                        'disponible'  => true,
                        'parametres'  => [
                            'T2M'            => ['label' => 'Température 2m (°C)',           'data' => $props['T2M']              ?? []],
                            'RH2M'           => ['label' => 'Humidité relative 2m (%)',      'data' => $props['RH2M']             ?? []],
                            'PRECTOTCORR'    => ['label' => 'Précipitations (mm/j)',          'data' => $props['PRECTOTCORR']      ?? []],
                            'WS2M'           => ['label' => 'Vitesse vent 2m (m/s)',          'data' => $props['WS2M']             ?? []],
                            'ALLSKY_SFC_SW_DWN' => ['label' => 'Rayonnement solaire (kWh/m²/j)','data' => $props['ALLSKY_SFC_SW_DWN'] ?? []],
                        ],
                    ];
                }
                return $this->indisponible('NASA POWER', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('NASA POWER error: ' . $e->getMessage());
                return $this->indisponible('NASA POWER', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // NASA EONET — Événements naturels
    // API : https://eonet.gsfc.nasa.gov/api/v3
    // Pas de clé requise
    // ─────────────────────────────────────────────────────────────────
    public function getEvenementsNaturelsNASA(int $jours = 30, string $categorie = ''): array
    {
        $cacheKey = 'nasa_eonet_' . md5("$jours,$categorie");
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($jours, $categorie) {
            try {
                $params = ['days' => $jours, 'status' => 'open', 'limit' => 50];
                if ($categorie) $params['category'] = $categorie;

                $response = Http::timeout(15)->get('https://eonet.gsfc.nasa.gov/api/v3/events', $params);

                if ($response->successful()) {
                    $events = $response->json('events') ?? [];
                    return [
                        'source'      => 'NASA EONET — Earth Observatory Natural Event Tracker',
                        'disponible'  => true,
                        'nb_events'   => count($events),
                        'categories'  => collect($events)->pluck('categories.0.title')->unique()->values(),
                        'evenements'  => collect($events)->map(fn($e) => [
                            'id'         => $e['id'],
                            'titre'      => $e['title'],
                            'categorie'  => $e['categories'][0]['title'] ?? null,
                            'date'       => $e['geometry'][0]['date'] ?? null,
                            'coordonnees'=> $e['geometry'][0]['coordinates'] ?? null,
                            'lien'       => $e['sources'][0]['url'] ?? null,
                        ])->toArray(),
                    ];
                }
                return $this->indisponible('NASA EONET', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('NASA EONET error: ' . $e->getMessage());
                return $this->indisponible('NASA EONET', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // ESA — Copernicus Open Access Hub / Sentinel Hub
    // API : https://catalogue.dataspace.copernicus.eu
    // Pas de clé pour catalogue (requise pour téléchargement)
    // ─────────────────────────────────────────────────────────────────
    public function getCatalogueESA(float $lat, float $lon, string $collection = 'SENTINEL-2', ?string $dateDebut = null): array
    {
        $dateDebut = $dateDebut ?? now()->subDays(30)->toISOString();
        $cacheKey  = 'esa_catalogue_' . md5("$lat,$lon,$collection,$dateDebut");

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($lat, $lon, $collection, $dateDebut) {
            try {
                $bbox     = implode(',', [$lon - 0.5, $lat - 0.5, $lon + 0.5, $lat + 0.5]);
                $response = Http::timeout(20)->get('https://catalogue.dataspace.copernicus.eu/odata/v1/Products', [
                    '$filter'  => "Collection/Name eq '{$collection}' and OData.CSC.Intersects(area=geography'SRID=4326;POINT({$lon} {$lat})') and ContentDate/Start gt {$dateDebut}",
                    '$orderby' => 'ContentDate/Start desc',
                    '$top'     => 10,
                ]);

                if ($response->successful()) {
                    $produits = $response->json('value') ?? [];
                    return [
                        'source'      => 'ESA — Copernicus Data Space Ecosystem',
                        'collection'  => $collection,
                        'latitude'    => $lat,
                        'longitude'   => $lon,
                        'disponible'  => true,
                        'nb_images'   => count($produits),
                        'produits'    => collect($produits)->map(fn($p) => [
                            'id'       => $p['Id']            ?? null,
                            'nom'      => $p['Name']          ?? null,
                            'date'     => $p['ContentDate']['Start'] ?? null,
                            'taille'   => $p['ContentLength'] ?? null,
                            'nuage_pct'=> $p['Attributes']['OData.CSC.DoubleAttribute']['Value'] ?? null,
                        ])->toArray(),
                        'note' => 'Téléchargement via compte Copernicus (gratuit sur dataspace.copernicus.eu)',
                    ];
                }
                return $this->indisponible('ESA Copernicus', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('ESA Copernicus error: ' . $e->getMessage());
                return $this->indisponible('ESA Copernicus', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Surveillance déforestation (NASA + ESA combinés)
    // ─────────────────────────────────────────────────────────────────
    public function getSurveillanceDeforestation(float $lat, float $lon): array
    {
        $incendies = $this->getIncendiesNASA($lat, $lon, 200);
        $images    = $this->getCatalogueESA($lat, $lon, 'SENTINEL-2');

        return [
            'source'        => 'EcoDash — Surveillance satellitaire intégrée',
            'latitude'      => $lat,
            'longitude'     => $lon,
            'incendies_72h' => $incendies,
            'images_s2'     => $images,
            'alerte'        => ($incendies['nb_incendies'] ?? 0) > 0
                ? "⚠ {$incendies['nb_incendies']} point(s) de chaleur détecté(s) dans un rayon de 200km"
                : "✓ Aucun incendie détecté dans la zone",
            'recommandation'=> ($incendies['nb_incendies'] ?? 0) > 0
                ? "Télécharger images Sentinel-2 pour analyse déforestation et vérification terrain"
                : "Surveillance normale — continuer monitoring mensuel",
        ];
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
