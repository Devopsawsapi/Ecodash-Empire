<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Biodiversité (Données Internationales)
 * Sources : GBIF (occurrences d'espèces), IUCN (statuts de conservation)
 */
class BiodiversityService
{
    // ─────────────────────────────────────────────────────────────────
    // IUCN — Statuts de conservation et espèces menacées
    // API : https://apiv3.iucnredlist.org/api/v3
    // Clé : IUCN_API_KEY (requise)
    // ─────────────────────────────────────────────────────────────────
    public function getStatutsIUCN(): array
    {
        return Cache::remember('iucn_statuts', now()->addDays(7), function () {
            return [
                'source'  => 'IUCN — Red List of Threatened Species v3',
                'url'     => 'https://apiv3.iucnredlist.org/api/v3',
                'categories' => [
                    'EX'  => ['label' => 'Éteint (Extinct)',                        'couleur' => '#000000', 'priorite' => 1],
                    'EW'  => ['label' => 'Éteint à l\'état sauvage',                'couleur' => '#542344', 'priorite' => 2],
                    'CR'  => ['label' => 'En danger critique (Critically Endangered)','couleur' => '#d40000','priorite' => 3],
                    'EN'  => ['label' => 'En danger (Endangered)',                   'couleur' => '#fc7f3f', 'priorite' => 4],
                    'VU'  => ['label' => 'Vulnérable (Vulnerable)',                  'couleur' => '#f9e814', 'priorite' => 5],
                    'NT'  => ['label' => 'Quasi menacé (Near Threatened)',           'couleur' => '#cce226', 'priorite' => 6],
                    'LC'  => ['label' => 'Préoccupation mineure (Least Concern)',    'couleur' => '#60c659', 'priorite' => 7],
                    'DD'  => ['label' => 'Données insuffisantes (Data Deficient)',   'couleur' => '#d1d1c6', 'priorite' => 8],
                    'NE'  => ['label' => 'Non évalué (Not Evaluated)',               'couleur' => '#ffffff', 'priorite' => 9],
                ],
                'note' => 'CR, EN et VU = espèces menacées d\'extinction selon critères IUCN',
            ];
        });
    }

    // IUCN — Espèces menacées d'une région
    public function getEspecesMenaceesIUCN(string $region = 'africa'): array
    {
        $cacheKey = 'iucn_menacees_' . $region;
        return Cache::remember($cacheKey, now()->addDays(1), function () use ($region) {
            $apiKey = config('services.iucn.key');
            if (!$apiKey) {
                return $this->indisponible('IUCN', 'Clé API manquante — ajouter IUCN_API_KEY dans .env');
            }
            try {
                $response = Http::timeout(15)->get("https://apiv3.iucnredlist.org/api/v3/species/region/{$region}/page/0", [
                    'token' => $apiKey,
                ]);

                if ($response->successful()) {
                    $especes = $response->json('result') ?? [];
                    $menacees = collect($especes)->filter(fn($e) => in_array($e['category'] ?? '', ['CR', 'EN', 'VU']));
                    return [
                        'source'      => 'IUCN Red List',
                        'region'      => $region,
                        'disponible'  => true,
                        'total'       => $response->json('count') ?? 0,
                        'menacees'    => $menacees->count(),
                        'especes'     => $menacees->take(50)->map(fn($e) => [
                            'id'             => $e['taxonid']      ?? null,
                            'nom_scientifique'=> $e['scientific_name'] ?? null,
                            'nom_commun'     => $e['main_common_name'] ?? null,
                            'categorie'      => $e['category']     ?? null,
                            'classe'         => $e['class_name']   ?? null,
                        ])->values()->toArray(),
                    ];
                }
                return $this->indisponible('IUCN', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('IUCN API error: ' . $e->getMessage());
                return $this->indisponible('IUCN', $e->getMessage());
            }
        });
    }

    // IUCN — Statut d'une espèce par nom scientifique
    public function getStatutEspeceIUCN(string $nomScientifique): array
    {
        $cacheKey = 'iucn_espece_' . md5($nomScientifique);
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($nomScientifique) {
            $apiKey = config('services.iucn.key');
            if (!$apiKey) {
                return $this->indisponible('IUCN', 'Clé API manquante');
            }
            try {
                $response = Http::timeout(10)->get(
                    'https://apiv3.iucnredlist.org/api/v3/species/' . urlencode($nomScientifique),
                    ['token' => $apiKey]
                );

                if ($response->successful()) {
                    $result = $response->json('result.0') ?? [];
                    $statuts = $this->getStatutsIUCN()['categories'];
                    $cat = $result['category'] ?? 'NE';
                    return [
                        'source'           => 'IUCN Red List',
                        'nom_scientifique' => $nomScientifique,
                        'disponible'       => true,
                        'taxonid'          => $result['taxonid']          ?? null,
                        'categorie'        => $cat,
                        'label_categorie'  => $statuts[$cat]['label']     ?? 'Non évalué',
                        'couleur'          => $statuts[$cat]['couleur']   ?? '#ffffff',
                        'est_menace'       => in_array($cat, ['CR', 'EN', 'VU']),
                        'famille'          => $result['family']           ?? null,
                        'classe'           => $result['class_name']       ?? null,
                        'population'       => $result['population_trend'] ?? null,
                    ];
                }
                return $this->indisponible('IUCN', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('IUCN species API error: ' . $e->getMessage());
                return $this->indisponible('IUCN', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // GBIF — Occurrences d'espèces par localisation
    // API : https://api.gbif.org/v1/
    // Pas de clé requise pour consultation (clé pour écriture)
    // ─────────────────────────────────────────────────────────────────
    public function getOccurrencesGBIF(float $lat, float $lon, float $rayon = 50, int $limit = 50): array
    {
        $cacheKey = 'gbif_occurrences_' . md5("$lat,$lon,$rayon,$limit");
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($lat, $lon, $rayon, $limit) {
            try {
                $response = Http::timeout(15)->get('https://api.gbif.org/v1/occurrence/search', [
                    'decimalLatitude'  => "$lat," . ($lat + deg2rad($rayon / 111.0) * (180 / M_PI)),
                    'decimalLongitude' => "$lon," . ($lon + deg2rad($rayon / 111.0) * (180 / M_PI)),
                    'limit'            => $limit,
                    'hasCoordinate'    => 'true',
                    'hasGeospatialIssue' => 'false',
                    'fields'           => 'key,scientificName,vernacularName,kingdom,class,order,family,species,decimalLatitude,decimalLongitude,eventDate,basisOfRecord',
                ]);

                if ($response->successful()) {
                    $results = $response->json('results') ?? [];
                    // Déduplique par espèce
                    $especes = collect($results)->groupBy('species')->map(function ($group, $espece) {
                        $first = $group->first();
                        return [
                            'espece'          => $espece,
                            'nom_vernaculaire'=> $first['vernacularName']  ?? null,
                            'classe'          => $first['class']           ?? null,
                            'famille'         => $first['family']          ?? null,
                            'occurrences'     => $group->count(),
                            'derniere_obs'    => $group->max('eventDate'),
                        ];
                    })->values();

                    return [
                        'source'       => 'GBIF — Global Biodiversity Information Facility',
                        'latitude'     => $lat,
                        'longitude'    => $lon,
                        'rayon_km'     => $rayon,
                        'disponible'   => true,
                        'total'        => $response->json('count') ?? 0,
                        'nb_especes'   => $especes->count(),
                        'especes'      => $especes->toArray(),
                    ];
                }
                return $this->indisponible('GBIF', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('GBIF API error: ' . $e->getMessage());
                return $this->indisponible('GBIF', $e->getMessage());
            }
        });
    }

    // GBIF — Recherche d'espèce par nom
    public function rechercherEspeceGBIF(string $nom): array
    {
        $cacheKey = 'gbif_espece_' . md5($nom);
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($nom) {
            try {
                $response = Http::timeout(10)->get('https://api.gbif.org/v1/species/suggest', [
                    'q'      => $nom,
                    'limit'  => 10,
                    'datasetKey' => 'd7dddbf4-2cf0-4f39-9b2a-bb099caae36c', // Checklist Bank
                ]);

                if ($response->successful()) {
                    return [
                        'source'     => 'GBIF',
                        'disponible' => true,
                        'resultats'  => collect($response->json() ?? [])->map(fn($e) => [
                            'key'             => $e['key']            ?? null,
                            'nom_scientifique'=> $e['scientificName'] ?? null,
                            'nom_vernaculaire'=> $e['vernacularName'] ?? null,
                            'rang'            => $e['rank']           ?? null,
                            'royaume'         => $e['kingdom']        ?? null,
                            'classe'          => $e['class']          ?? null,
                        ])->toArray(),
                    ];
                }
                return $this->indisponible('GBIF', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('GBIF search error: ' . $e->getMessage());
                return $this->indisponible('GBIF', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Comparaison observation locale vs IUCN
    // ─────────────────────────────────────────────────────────────────
    public function comparerObservationLocale(array $observation): array
    {
        $nomScientifique = $observation['nom_scientifique'] ?? null;
        if (!$nomScientifique) {
            return ['erreur' => 'Nom scientifique requis pour la comparaison IUCN'];
        }

        $statutIUCN    = $this->getStatutEspeceIUCN($nomScientifique);
        $statuts       = $this->getStatutsIUCN()['categories'];
        $catLocale     = $observation['statut_iucn'] ?? 'NE';
        $catInternat   = $statutIUCN['categorie']    ?? 'NE';
        $prioriteLocal = $statuts[$catLocale]['priorite']   ?? 9;
        $prioriteInt   = $statuts[$catInternat]['priorite'] ?? 9;

        return [
            'source_locale'     => 'EcoDash — Observation de terrain',
            'source_reference'  => 'IUCN Red List v3',
            'espece'            => $nomScientifique,
            'statut_local'      => [
                'categorie' => $catLocale,
                'label'     => $statuts[$catLocale]['label']   ?? 'Non évalué',
                'couleur'   => $statuts[$catLocale]['couleur'] ?? '#ffffff',
            ],
            'statut_iucn'       => $statutIUCN,
            'cohérence'         => $catLocale === $catInternat ? 'Conforme' : 'Divergent',
            'alerte'            => $prioriteLocal > $prioriteInt
                ? "⚠ Statut local ({$catLocale}) moins sévère que IUCN ({$catInternat}) — réévaluation recommandée"
                : null,
            'priorite_conservation' => $prioriteInt <= 5 ? 'haute' : ($prioriteInt <= 7 ? 'modérée' : 'faible'),
        ];
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
