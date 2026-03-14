<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Qualité de l'Eau (Données Internationales)
 * Sources : OMS (normes), USGS (cours d'eau), WRI Aqueduct (stress hydrique), NOAA (marine)
 */
class WaterQualityService
{
    // ─────────────────────────────────────────────────────────────────
    // OMS — Normes eau potable (4e édition)
    // ─────────────────────────────────────────────────────────────────
    public function getNormesOMS(): array
    {
        return Cache::remember('oms_eau_normes', now()->addDays(7), function () {
            return [
                'source'  => 'OMS — Guidelines for Drinking-water Quality 4th ed. 2017 + addendum 2022',
                'url'     => 'https://www.who.int/publications/i/item/9789241549950',
                'normes'  => [
                    'ph'              => ['min' => 6.5,    'max' => 8.5,    'unite' => '',          'description' => 'Acidité / alcalinité',              'critique' => false],
                    'turbidite'       => ['min' => null,   'max' => 4.0,    'unite' => 'NTU',       'description' => 'Turbidité',                         'critique' => false],
                    'oxygene_dissous' => ['min' => 5.0,    'max' => null,   'unite' => 'mg/L',      'description' => 'Oxygène dissous (minimum)',          'critique' => false],
                    'nitrates'        => ['min' => null,   'max' => 50.0,   'unite' => 'mg/L',      'description' => 'Nitrates (NO₃)',                    'critique' => false],
                    'dbo5'            => ['min' => null,   'max' => 5.0,    'unite' => 'mg/L O₂',   'description' => 'Demande Biochimique en Oxygène 5j',  'critique' => false],
                    'plomb'           => ['min' => null,   'max' => 0.01,   'unite' => 'mg/L',      'description' => 'Plomb (Pb)',                         'critique' => true],
                    'mercure'         => ['min' => null,   'max' => 0.006,  'unite' => 'mg/L',      'description' => 'Mercure total (Hg)',                 'critique' => true],
                    'arsenic'         => ['min' => null,   'max' => 0.01,   'unite' => 'mg/L',      'description' => 'Arsenic (As)',                       'critique' => true],
                    'cadmium'         => ['min' => null,   'max' => 0.003,  'unite' => 'mg/L',      'description' => 'Cadmium (Cd)',                       'critique' => true],
                    'chrome'          => ['min' => null,   'max' => 0.05,   'unite' => 'mg/L',      'description' => 'Chrome total (Cr)',                  'critique' => true],
                    'zinc'            => ['min' => null,   'max' => 3.0,    'unite' => 'mg/L',      'description' => 'Zinc (Zn)',                          'critique' => false],
                    'fer'             => ['min' => null,   'max' => 0.3,    'unite' => 'mg/L',      'description' => 'Fer total (Fe)',                     'critique' => false],
                    'manganese'       => ['min' => null,   'max' => 0.4,    'unite' => 'mg/L',      'description' => 'Manganèse (Mn)',                     'critique' => false],
                    'fluorures'       => ['min' => null,   'max' => 1.5,    'unite' => 'mg/L',      'description' => 'Fluorures',                          'critique' => false],
                    'coliformes_totaux' => ['min' => null, 'max' => 0,      'unite' => 'UFC/100mL', 'description' => 'Coliformes totaux',                  'critique' => false],
                    'e_coli'          => ['min' => null,   'max' => 0,      'unite' => 'UFC/100mL', 'description' => 'E. coli (contamination fécale)',     'critique' => true],
                ],
                'classification_iqe' => [
                    ['min' => 90, 'max' => 100, 'niveau' => 'Excellent',  'couleur' => '#00e400'],
                    ['min' => 70, 'max' => 89,  'niveau' => 'Bon',        'couleur' => '#92d050'],
                    ['min' => 50, 'max' => 69,  'niveau' => 'Moyen',      'couleur' => '#ffff00'],
                    ['min' => 25, 'max' => 49,  'niveau' => 'Mauvais',    'couleur' => '#ff7e00'],
                    ['min' => 0,  'max' => 24,  'niveau' => 'Très mauvais','couleur' => '#ff0000'],
                ],
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // USGS — National Water Information System
    // API : https://waterservices.usgs.gov/nwis/iv/
    // Pas de clé requise (données publiques US)
    // Paramètres : 00060=débit, 00065=niveau, 00010=température, 00300=O₂, 00400=pH
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesUSGS(string $siteCode = '09380000'): array
    {
        $cacheKey = 'usgs_water_' . $siteCode;
        return Cache::remember($cacheKey, now()->addHours(2), function () use ($siteCode) {
            try {
                $response = Http::timeout(15)->get('https://waterservices.usgs.gov/nwis/iv/', [
                    'sites'       => $siteCode,
                    'parameterCd' => '00060,00065,00010,00300,00400',
                    'siteStatus'  => 'active',
                    'format'      => 'json',
                ]);

                if ($response->successful()) {
                    $series = $response->json('value.timeSeries') ?? [];
                    $mesures = [];
                    foreach ($series as $ts) {
                        $nom    = $ts['variable']['variableName']  ?? '';
                        $code   = $ts['variable']['variableCode.0.value'] ?? '';
                        $valeur = $ts['values'][0]['value'][0]['value']  ?? null;
                        $unite  = $ts['variable']['unit']['unitCode']    ?? '';
                        $date   = $ts['values'][0]['value'][0]['dateTime'] ?? null;
                        $mesures[] = ['parametre' => $nom, 'code' => $code, 'valeur' => $valeur, 'unite' => $unite, 'date' => $date];
                    }

                    $info = $response->json('value.timeSeries.0.sourceInfo') ?? [];
                    return [
                        'source'      => 'USGS — National Water Information System',
                        'site_code'   => $siteCode,
                        'site_nom'    => $info['siteName'] ?? 'Site USGS',
                        'latitude'    => $info['geoLocation']['geogLocation']['latitude']  ?? null,
                        'longitude'   => $info['geoLocation']['geogLocation']['longitude'] ?? null,
                        'disponible'  => true,
                        'mesures'     => $mesures,
                        'note'        => 'Données temps réel US — utiliser pour calibration et comparaison de référence',
                    ];
                }
                return $this->indisponible('USGS', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('USGS API error: ' . $e->getMessage());
                return $this->indisponible('USGS', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // WRI Aqueduct — Stress hydrique mondial
    // API : https://api.resourcewatch.org
    // Clé : WRI_API_KEY (optionnelle pour certains endpoints)
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesWRI(string $pays = 'COD'): array
    {
        $cacheKey = 'wri_water_' . $pays;
        return Cache::remember($cacheKey, now()->addDays(1), function () use ($pays) {
            try {
                $headers = [];
                $apiKey  = config('services.wri.key');
                if ($apiKey) $headers['x-api-key'] = $apiKey;

                // Dataset Aqueduct Water Risk Atlas
                $response = Http::timeout(15)->withHeaders($headers)
                    ->get('https://api.resourcewatch.org/v1/dataset', [
                        'env'    => 'production',
                        'status' => 'saved',
                        'search' => 'aqueduct water risk',
                        'page[size]' => 5,
                    ]);

                if ($response->successful()) {
                    $datasets = $response->json('data') ?? [];
                    return [
                        'source'      => 'WRI — World Resources Institute / Aqueduct',
                        'pays'        => $pays,
                        'disponible'  => true,
                        'datasets'    => collect($datasets)->map(fn($d) => [
                            'id'          => $d['id'],
                            'nom'         => $d['attributes']['name'] ?? null,
                            'description' => substr($d['attributes']['description'] ?? '', 0, 300),
                            'url_data'    => 'https://api.resourcewatch.org/v1/dataset/' . $d['id'] . '/data',
                        ])->toArray(),
                        'indicateurs_disponibles' => [
                            'stress_hydrique'     => 'Niveau de stress hydrique par bassin versant',
                            'disponibilite_eau'   => 'Disponibilité annuelle d\'eau renouvelable',
                            'pollution_rivieres'  => 'Indice de pollution des cours d\'eau',
                            'inondation_risque'   => 'Risque d\'inondation côtière et fluviale',
                        ],
                    ];
                }
                return $this->indisponible('WRI', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('WRI API error: ' . $e->getMessage());
                return $this->indisponible('WRI', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // NOAA — Températures et niveaux marins
    // API : https://api.tidesandcurrents.noaa.gov/api/prod/datagetter
    // Pas de clé requise
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesNOAA(string $stationId = '8518750', int $jours = 7): array
    {
        $cacheKey = 'noaa_water_' . $stationId;
        return Cache::remember($cacheKey, now()->addHours(2), function () use ($stationId, $jours) {
            try {
                $response = Http::timeout(15)->get('https://api.tidesandcurrents.noaa.gov/api/prod/datagetter', [
                    'station'     => $stationId,
                    'product'     => 'water_temperature',
                    'date'        => 'latest',
                    'datum'       => 'MLLW',
                    'time_zone'   => 'GMT',
                    'units'       => 'metric',
                    'application' => 'ecodash_etn',
                    'format'      => 'json',
                ]);

                if ($response->successful() && !isset($response->json()['error'])) {
                    $data = $response->json('data') ?? [];
                    $dernier = end($data);
                    return [
                        'source'       => 'NOAA — National Ocean Service',
                        'station_id'   => $stationId,
                        'disponible'   => true,
                        'temperature'  => $dernier['v'] ?? null,
                        'unite'        => '°C',
                        'horodatage'   => $dernier['t'] ?? null,
                        'historique'   => collect($data)->map(fn($d) => ['t' => $d['t'], 'v' => $d['v']])->take(10)->values(),
                        'note'         => 'Station NOAA US — référence océanographique mondiale',
                    ];
                }
                $erreur = $response->json('error.message') ?? 'Réponse invalide';
                return $this->indisponible('NOAA', $erreur);
            } catch (\Exception $e) {
                Log::warning('NOAA API error: ' . $e->getMessage());
                return $this->indisponible('NOAA', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Comparaison : mesure locale EcoDash vs normes OMS eau potable
    // ─────────────────────────────────────────────────────────────────
    public function comparerAvecNormes(array $mesureLocale): array
    {
        $normes = $this->getNormesOMS()['normes'];
        $comparaisons = [];

        foreach ($normes as $parametre => $norme) {
            if (!isset($mesureLocale[$parametre]) || $mesureLocale[$parametre] === null) continue;

            $valeur   = (float) $mesureLocale[$parametre];
            $conforme = true;
            $statut   = 'conforme';
            $ratio    = null;

            if ($norme['min'] !== null && $valeur < $norme['min']) {
                $conforme = false;
                $statut   = 'sous_seuil';
                $ratio    = $norme['min'] > 0 ? round($valeur / $norme['min'], 3) : null;
            }
            if ($norme['max'] !== null && $valeur > $norme['max']) {
                $conforme = false;
                $ratio    = $norme['max'] > 0 ? round($valeur / $norme['max'], 3) : null;
                $statut   = ($ratio && $ratio > 5) || $norme['critique'] ? 'critique' : 'attention';
            }

            $comparaisons[$parametre] = [
                'valeur_locale'   => $valeur,
                'norme_min'       => $norme['min'],
                'norme_max'       => $norme['max'],
                'unite'           => $norme['unite'],
                'description'     => $norme['description'],
                'est_critique'    => $norme['critique'],
                'ratio'           => $ratio,
                'conforme'        => $conforme,
                'statut'          => $statut,
                'recommandation'  => $this->recommandationEau($parametre, $valeur, $norme, $conforme),
            ];
        }

        $nonConformes   = array_filter($comparaisons, fn($c) => !$c['conforme']);
        $critiques      = array_filter($nonConformes, fn($c)  => $c['est_critique']);

        return [
            'type'               => 'eau',
            'source_locale'      => 'EcoDash — Prélèvement terrain',
            'source_reference'   => 'OMS — Guidelines for Drinking-water Quality',
            'date_mesure'        => $mesureLocale['date_prelevement'] ?? now()->toISOString(),
            'site'               => $mesureLocale['site']    ?? null,
            'site_id'            => $mesureLocale['site_id'] ?? null,
            'comparaisons'       => $comparaisons,
            'nb_non_conformes'   => count($nonConformes),
            'parametres_critiques' => array_keys($critiques),
            'conformite_globale' => count($nonConformes) === 0,
            'statut_global'      => count($critiques) > 0 ? 'critique' : (count($nonConformes) > 0 ? 'attention' : 'conforme'),
            'iqe_local'          => $mesureLocale['indice_qualite'] ?? null,
        ];
    }

    private function recommandationEau(string $param, float $valeur, array $norme, bool $conforme): string
    {
        if ($conforme) return 'Valeur conforme aux normes OMS.';
        $messages = [
            'ph'              => 'pH hors norme — Risque corrosion ou précipitation. Traitement alcalinisant ou acidifiant requis.',
            'turbidite'       => 'Turbidité élevée — Filtration insuffisante. Risque microbiologique accru.',
            'plomb'           => '⚠ ALERTE PLOMB — Contamination grave. Interdire consommation immédiatement. Identifier source.',
            'mercure'         => '⚠ ALERTE MERCURE — Très toxique. Fermeture du site requise. Analyse en laboratoire urgente.',
            'arsenic'         => '⚠ ALERTE ARSENIC — Cancérigène. Consommation interdite. Investigation géologique et industrielle.',
            'cadmium'         => '⚠ ALERTE CADMIUM — Toxique renal. Source industrielle probable. Enquête requise.',
            'e_coli'          => '⚠ CONTAMINATION FÉCALE — E. coli détecté. Eau impropre à la consommation. Chloration et enquête sanitaire.',
            'coliformes_totaux' => 'Coliformes présents — Désinfection insuffisante ou recontamination post-traitement.',
            'oxygene_dissous' => 'Oxygène insuffisant — Eutrophisation probable. Risque mortalité piscicole.',
            'nitrates'        => 'Nitrates élevés — Source agricole probable. Risque méthémoglobinémie (nourrissons).',
        ];
        return $messages[$param] ?? "Dépassement norme OMS ({$param}) — investigation requise.";
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
