<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Qualité de l'Air (Données Internationales)
 * Sources : OMS (normes), IQAir (données réelles), OpenWeather (données réelles)
 */
class AirQualityService
{
    // ─────────────────────────────────────────────────────────────────
    // OMS — Normes qualité de l'air 2021
    // API : https://ghoapi.azureedge.net/api
    // Pas de clé requise — données publiques
    // ─────────────────────────────────────────────────────────────────
    public function getNormesOMS(): array
    {
        return Cache::remember('oms_air_normes', now()->addDays(7), function () {
            try {
                // Tenter de récupérer les indicateurs OMS depuis GHO
                $response = Http::timeout(10)->get('https://ghoapi.azureedge.net/api/Indicator', [
                    '$filter' => "contains(IndicatorName, 'air')",
                    '$top'    => 5,
                ]);
            } catch (\Exception $e) {
                Log::info('OMS GHO API unreachable, using embedded norms');
            }

            // Normes OMS 2021 intégrées (guidelines officiels)
            return [
                'source'    => 'OMS — WHO Air Quality Guidelines 2021',
                'url'       => 'https://ghoapi.azureedge.net/api',
                'annee'     => 2021,
                'methode'   => 'WHO guidelines + intégrées localement',
                'normes'    => [
                    'pm25'  => ['valeur_annuelle' => 5,    'valeur_24h' => 15,   'unite' => 'µg/m³', 'description' => 'Particules fines PM2.5 — risque cardiovasculaire et pulmonaire'],
                    'pm10'  => ['valeur_annuelle' => 15,   'valeur_24h' => 45,   'unite' => 'µg/m³', 'description' => 'Particules PM10 — affections respiratoires'],
                    'no2'   => ['valeur_annuelle' => 10,   'valeur_24h' => 25,   'unite' => 'µg/m³', 'description' => 'Dioxyde d\'azote — inflammation pulmonaire'],
                    'o3'    => ['valeur_8h'       => 60,   'valeur_pic' => 100,  'unite' => 'µg/m³', 'description' => 'Ozone troposphérique — atteintes respiratoires'],
                    'so2'   => ['valeur_24h'      => 40,   'unite' => 'µg/m³',  'description' => 'Dioxyde de soufre — irritations voies respiratoires'],
                    'co'    => ['valeur_24h'      => 4000, 'unite' => 'µg/m³',  'description' => 'Monoxyde de carbone'],
                ],
                'echelle_iqa' => [
                    ['min' => 0,   'max' => 50,  'niveau' => 'Bon',                   'couleur' => '#00e400', 'impact' => 'Aucun risque pour la santé'],
                    ['min' => 51,  'max' => 100, 'niveau' => 'Modéré',                'couleur' => '#ffff00', 'impact' => 'Risque pour personnes sensibles'],
                    ['min' => 101, 'max' => 150, 'niveau' => 'Mauvais (GSP)',          'couleur' => '#ff7e00', 'impact' => 'Problèmes pour groupes sensibles'],
                    ['min' => 151, 'max' => 200, 'niveau' => 'Mauvais',               'couleur' => '#ff0000', 'impact' => 'Effets sur la population générale'],
                    ['min' => 201, 'max' => 300, 'niveau' => 'Très mauvais',           'couleur' => '#8f3f97', 'impact' => 'Alertes sanitaires sévères'],
                    ['min' => 301, 'max' => 500, 'niveau' => 'Dangereux',              'couleur' => '#7e0023', 'impact' => 'Urgence sanitaire — toute la population'],
                ],
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // IQAir — AQI et polluants par ville
    // API : https://api.airvisual.com/v2/
    // Clé : IQAIR_API_KEY
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesIQAir(string $ville, string $etat = '', string $pays = 'Congo'): array
    {
        $cacheKey = 'iqair_' . md5($ville . $etat . $pays);
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($ville, $etat, $pays) {
            $apiKey = config('services.iqair.key');
            if (!$apiKey) {
                return $this->indisponible('IQAir', 'Clé API manquante — ajouter IQAIR_API_KEY dans .env');
            }
            try {
                $response = Http::timeout(12)->get('https://api.airvisual.com/v2/city', [
                    'city'    => $ville,
                    'state'   => $etat ?: $pays,
                    'country' => $pays,
                    'key'     => $apiKey,
                ]);
                if ($response->successful()) {
                    $d = $response->json('data');
                    return [
                        'source'     => 'IQAir — AirVisual',
                        'ville'      => $d['city'] ?? $ville,
                        'pays'       => $d['country'] ?? $pays,
                        'disponible' => true,
                        'mesures'    => [
                            'aqi_us'  => $d['current']['pollution']['aqius']  ?? null,
                            'aqi_cn'  => $d['current']['pollution']['aqicn']  ?? null,
                            'pm25'    => $d['current']['pollution']['p2']     ?? null,
                            'pm10'    => $d['current']['pollution']['p1']     ?? null,
                            'co'      => $d['current']['pollution']['co']     ?? null,
                            'o3'      => $d['current']['pollution']['o3']     ?? null,
                            'no2'     => $d['current']['pollution']['n2']     ?? null,
                            'so2'     => $d['current']['pollution']['s2']     ?? null,
                        ],
                        'meteo' => [
                            'temperature' => $d['current']['weather']['tp'] ?? null,
                            'humidite'    => $d['current']['weather']['hu'] ?? null,
                            'pression'    => $d['current']['weather']['pr'] ?? null,
                            'vent_ms'     => $d['current']['weather']['ws'] ?? null,
                        ],
                        'horodatage' => $d['current']['pollution']['ts'] ?? null,
                    ];
                }
                return $this->indisponible('IQAir', 'HTTP ' . $response->status() . ': ' . $response->body());
            } catch (\Exception $e) {
                Log::warning('IQAir API error: ' . $e->getMessage());
                return $this->indisponible('IQAir', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // OpenWeatherMap — Air Pollution API
    // API : https://api.openweathermap.org/data/2.5/air_pollution
    // Clé : OPENWEATHER_API_KEY
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesOpenWeather(float $lat, float $lon): array
    {
        $cacheKey = 'openweather_air_' . md5("$lat,$lon");
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($lat, $lon) {
            $apiKey = config('services.openweather.key');
            if (!$apiKey) {
                return $this->indisponible('OpenWeather', 'Clé API manquante — ajouter OPENWEATHER_API_KEY dans .env');
            }
            try {
                $response = Http::timeout(12)->get('https://api.openweathermap.org/data/2.5/air_pollution', [
                    'lat'   => $lat,
                    'lon'   => $lon,
                    'appid' => $apiKey,
                ]);
                if ($response->successful()) {
                    $item = $response->json('list.0') ?? [];
                    $comp = $item['components'] ?? [];
                    return [
                        'source'     => 'OpenWeatherMap — Air Pollution API',
                        'latitude'   => $lat,
                        'longitude'  => $lon,
                        'disponible' => true,
                        'mesures'    => [
                            'aqi_owm' => $item['main']['aqi'] ?? null,  // 1-5
                            'co'      => $comp['co']    ?? null,         // µg/m³
                            'no'      => $comp['no']    ?? null,
                            'no2'     => $comp['no2']   ?? null,
                            'o3'      => $comp['o3']    ?? null,
                            'so2'     => $comp['so2']   ?? null,
                            'pm25'    => $comp['pm2_5'] ?? null,
                            'pm10'    => $comp['pm10']  ?? null,
                            'nh3'     => $comp['nh3']   ?? null,
                        ],
                        'horodatage' => $item['dt'] ?? null,
                    ];
                }
                return $this->indisponible('OpenWeather', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('OpenWeather Air API error: ' . $e->getMessage());
                return $this->indisponible('OpenWeather', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Comparaison : mesure locale EcoDash vs normes OMS
    // ─────────────────────────────────────────────────────────────────
    public function comparerAvecNormes(array $mesureLocale): array
    {
        $normes = $this->getNormesOMS()['normes'];
        $comparaisons = [];

        $mapping = [
            'pm25' => ['norme_key' => 'pm25',  'seuil_key' => 'valeur_24h'],
            'pm10' => ['norme_key' => 'pm10',  'seuil_key' => 'valeur_24h'],
            'no2'  => ['norme_key' => 'no2',   'seuil_key' => 'valeur_24h'],
            'o3'   => ['norme_key' => 'o3',    'seuil_key' => 'valeur_8h'],
            'so2'  => ['norme_key' => 'so2',   'seuil_key' => 'valeur_24h'],
            'co'   => ['norme_key' => 'co',    'seuil_key' => 'valeur_24h'],
        ];

        foreach ($mapping as $champ => $config) {
            if (!isset($mesureLocale[$champ]) || $mesureLocale[$champ] === null) continue;

            $valeur     = (float) $mesureLocale[$champ];
            $norme      = $normes[$config['norme_key']] ?? [];
            $seuilRef   = (float) ($norme[$config['seuil_key']] ?? 0);
            $ratio      = $seuilRef > 0 ? round($valeur / $seuilRef, 3) : null;
            $depPct     = $ratio && $ratio > 1 ? round(($ratio - 1) * 100, 1) : 0;

            $comparaisons[$champ] = [
                'valeur_locale'   => $valeur,
                'norme_oms'       => $seuilRef,
                'periode_norme'   => $config['seuil_key'],
                'unite'           => $norme['unite'] ?? 'µg/m³',
                'description'     => $norme['description'] ?? '',
                'ratio'           => $ratio,
                'conforme'        => $ratio !== null && $ratio <= 1.0,
                'depassement_pct' => $depPct,
                'statut'          => $this->statut($ratio),
                'recommandation'  => $this->recommandationAir($champ, $ratio),
            ];
        }

        $nonConformes = array_filter($comparaisons, fn($c) => !$c['conforme']);

        return [
            'type'               => 'air',
            'source_locale'      => 'EcoDash — Mesure terrain',
            'source_reference'   => 'OMS — WHO AQG 2021',
            'date_mesure'        => $mesureLocale['date_mesure'] ?? now()->toISOString(),
            'site'               => $mesureLocale['site'] ?? null,
            'site_id'            => $mesureLocale['site_id'] ?? null,
            'comparaisons'       => $comparaisons,
            'nb_non_conformes'   => count($nonConformes),
            'polluants_hors_norme' => array_keys($nonConformes),
            'conformite_globale' => count($nonConformes) === 0,
            'statut_global'      => count($nonConformes) === 0 ? 'conforme' : (count($nonConformes) <= 2 ? 'attention' : 'critique'),
            'iqa_local'          => $mesureLocale['iqa'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────
    private function statut(?float $ratio): string
    {
        if ($ratio === null) return 'inconnu';
        if ($ratio <= 0.5)   return 'excellent';
        if ($ratio <= 1.0)   return 'conforme';
        if ($ratio <= 1.5)   return 'attention';
        if ($ratio <= 2.0)   return 'mauvais';
        return 'critique';
    }

    private function recommandationAir(string $polluant, ?float $ratio): string
    {
        if ($ratio === null || $ratio <= 1.0) return 'Valeur dans la norme OMS.';
        $messages = [
            'pm25' => 'PM2.5 élevé — Vérifier sources d\'émission (trafic, industrie). Port de masque FFP2 recommandé.',
            'pm10' => 'PM10 élevé — Surveiller activités génératrices de poussières. Arrosage des pistes conseillé.',
            'no2'  => 'NO₂ élevé — Réduire émissions véhicules et combustion. Ventilation insuffisante.',
            'so2'  => 'SO₂ critique — Identifier source industrielle immédiatement. Risque d\'acidification.',
            'o3'   => 'O₃ élevé — Phénomène photochimique. Limiter activités extérieures en journée.',
            'co'   => 'CO élevé — Danger asphyxie. Identifier source de combustion incomplète en urgence.',
        ];
        return $messages[$polluant] ?? 'Dépassement de norme — investigation requise.';
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
