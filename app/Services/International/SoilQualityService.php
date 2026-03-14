<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Qualité des Sols (Données Internationales)
 * Sources : FAO (normes VGSSM), ISRIC SoilGrids (données réelles)
 */
class SoilQualityService
{
    // ─────────────────────────────────────────────────────────────────
    // FAO — Normes sol (VGSSM 2017 + directives EU/ISO)
    // ─────────────────────────────────────────────────────────────────
    public function getNormesFAO(): array
    {
        return Cache::remember('fao_sol_normes', now()->addDays(7), function () {
            return [
                'source'  => 'FAO — Voluntary Guidelines for Sustainable Soil Management (VGSSM 2017)',
                'url'     => 'https://data.apps.fao.org',
                'normes'  => [
                    'ph_sol'             => ['min' => 5.5,   'max' => 8.0,   'unite' => '',       'description' => 'pH sol — plage agricole optimale',            'critique' => false],
                    'matiere_organique'  => ['min' => 2.0,   'max' => null,  'unite' => '%',      'description' => 'Matière organique (minimum recommandé)',       'critique' => false],
                    'azote_total'        => ['min' => 0.1,   'max' => null,  'unite' => '%',      'description' => 'Azote total',                                  'critique' => false],
                    'phosphore_dispo'    => ['min' => 10.0,  'max' => null,  'unite' => 'mg/kg',  'description' => 'Phosphore assimilable (Olsen)',                 'critique' => false],
                    'plomb_sol'          => ['min' => null,  'max' => 100.0, 'unite' => 'mg/kg',  'description' => 'Plomb (Pb) — seuil sol agricole',              'critique' => true],
                    'cadmium_sol'        => ['min' => null,  'max' => 2.0,   'unite' => 'mg/kg',  'description' => 'Cadmium (Cd)',                                 'critique' => true],
                    'arsenic_sol'        => ['min' => null,  'max' => 40.0,  'unite' => 'mg/kg',  'description' => 'Arsenic (As)',                                 'critique' => true],
                    'mercure_sol'        => ['min' => null,  'max' => 1.0,   'unite' => 'mg/kg',  'description' => 'Mercure (Hg)',                                 'critique' => true],
                    'chrome_sol'         => ['min' => null,  'max' => 150.0, 'unite' => 'mg/kg',  'description' => 'Chrome total (Cr)',                            'critique' => true],
                    'nickel_sol'         => ['min' => null,  'max' => 75.0,  'unite' => 'mg/kg',  'description' => 'Nickel (Ni)',                                  'critique' => false],
                    'zinc_sol'           => ['min' => null,  'max' => 300.0, 'unite' => 'mg/kg',  'description' => 'Zinc (Zn)',                                    'critique' => false],
                    'cuivre_sol'         => ['min' => null,  'max' => 100.0, 'unite' => 'mg/kg',  'description' => 'Cuivre (Cu)',                                  'critique' => false],
                    'hydrocarbures_totaux' => ['min' => null,'max' => 500.0, 'unite' => 'mg/kg',  'description' => 'Hydrocarbures totaux (C10-C40)',               'critique' => true],
                    'pesticides_totaux'  => ['min' => null,  'max' => 1.0,   'unite' => 'mg/kg',  'description' => 'Pesticides totaux (organochlorés)',            'critique' => true],
                    'pcb_totaux'         => ['min' => null,  'max' => 0.1,   'unite' => 'mg/kg',  'description' => 'Polychlorobiphényles (PCB)',                   'critique' => true],
                ],
                'classification_erosion' => [
                    'nulle'     => ['iqs_impact' => 0,  'description' => 'Pas d\'érosion visible'],
                    'faible'    => ['iqs_impact' => -5, 'description' => 'Érosion superficielle légère'],
                    'modere'    => ['iqs_impact' => -15,'description' => 'Érosion notable — couverture végétale recommandée'],
                    'fort'      => ['iqs_impact' => -25,'description' => 'Érosion sévère — intervention urgente'],
                    'tres_fort' => ['iqs_impact' => -40,'description' => 'Dégradation avancée — risque stérilisation'],
                ],
                'classification_texture' => [
                    'sableux'          => 'Drainage rapide, faible rétention hydrique et nutriments',
                    'limoneux'         => 'Équilibré — bonne structure agricole',
                    'argileux'         => 'Rétention élevée — drainage lent, compaction possible',
                    'franco-limoneux'  => 'Idéal cultures — bonne rétention et drainage',
                    'franco-argileux'  => 'Fertile mais compact — labour conseillé',
                ],
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // ISRIC SoilGrids 2.0 — Données sol mondiales
    // API : https://rest.isric.org/soilgrids/v2.0/
    // Pas de clé requise — données publiques
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesISRIC(float $lat, float $lon, string $profondeur = '0-5cm'): array
    {
        $cacheKey = 'isric_sol_' . md5("$lat,$lon,$profondeur");
        return Cache::remember($cacheKey, now()->addDays(3), function () use ($lat, $lon, $profondeur) {
            try {
                $response = Http::timeout(20)->get('https://rest.isric.org/soilgrids/v2.0/properties/query', [
                    'lon'      => $lon,
                    'lat'      => $lat,
                    'property' => ['phh2o', 'soc', 'nitrogen', 'clay', 'sand', 'silt', 'bdod', 'cec', 'cfvo'],
                    'depth'    => $profondeur,
                    'value'    => 'mean',
                ]);

                if ($response->successful()) {
                    $layers = $response->json('properties.layers') ?? [];
                    $mesures = [];
                    foreach ($layers as $layer) {
                        $nom     = $layer['name'] ?? '';
                        $valeur  = $layer['depths'][0]['values']['mean'] ?? null;
                        $factor  = $this->facteurConversion($nom);
                        $mesures[$nom] = [
                            'valeur_brute'    => $valeur,
                            'valeur_convertie'=> $valeur !== null ? round($valeur * $factor['mult'] + $factor['add'], 4) : null,
                            'unite'           => $factor['unite'],
                            'label'           => $this->labelISRIC($nom),
                        ];
                    }
                    return [
                        'source'     => 'ISRIC — SoilGrids 2.0',
                        'latitude'   => $lat,
                        'longitude'  => $lon,
                        'profondeur' => $profondeur,
                        'disponible' => true,
                        'mesures'    => $mesures,
                        'legende'    => 'Données modélisées à 250m de résolution — valeurs indicatives',
                    ];
                }
                return $this->indisponible('ISRIC', 'HTTP ' . $response->status() . ' — ' . $response->body());
            } catch (\Exception $e) {
                Log::warning('ISRIC SoilGrids API error: ' . $e->getMessage());
                return $this->indisponible('ISRIC', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // FAO GeoNetwork — Jeux de données sol
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesFAO(string $requete = 'soil contamination'): array
    {
        $cacheKey = 'fao_sol_datasets_' . md5($requete);
        return Cache::remember($cacheKey, now()->addDays(3), function () use ($requete) {
            try {
                $response = Http::timeout(15)->get('https://data.apps.fao.org/catalog/api/3/action/package_search', [
                    'q'    => $requete,
                    'rows' => 8,
                    'start'=> 0,
                ]);

                if ($response->successful()) {
                    $results = $response->json('result.results') ?? [];
                    return [
                        'source'     => 'FAO — Global Soil Partnership / GeoNetwork',
                        'disponible' => true,
                        'requete'    => $requete,
                        'total'      => $response->json('result.count') ?? 0,
                        'datasets'   => collect($results)->map(fn($r) => [
                            'titre'       => $r['title']  ?? null,
                            'description' => substr($r['notes'] ?? '', 0, 250),
                            'format'      => collect($r['resources'] ?? [])->pluck('format')->unique()->values(),
                            'url'         => 'https://data.apps.fao.org/catalog/dataset/' . ($r['name'] ?? ''),
                        ])->toArray(),
                    ];
                }
                return $this->indisponible('FAO', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('FAO API error: ' . $e->getMessage());
                return $this->indisponible('FAO', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Comparaison : mesure sol locale EcoDash vs normes FAO
    // ─────────────────────────────────────────────────────────────────
    public function comparerAvecNormes(array $mesureLocale): array
    {
        $normes = $this->getNormesFAO()['normes'];
        $comparaisons = [];

        foreach ($normes as $parametre => $norme) {
            if (!isset($mesureLocale[$parametre]) || $mesureLocale[$parametre] === null) continue;

            $valeur   = (float) $mesureLocale[$parametre];
            $conforme = true;
            $statut   = 'conforme';
            $ratio    = null;

            if ($norme['min'] !== null && $valeur < $norme['min']) {
                $conforme = false;
                $statut   = 'carencé';
                $ratio    = $norme['min'] > 0 ? round($valeur / $norme['min'], 3) : null;
            }
            if ($norme['max'] !== null && $valeur > $norme['max']) {
                $conforme = false;
                $ratio    = $norme['max'] > 0 ? round($valeur / $norme['max'], 3) : null;
                if ($norme['critique']) {
                    $statut = $ratio && $ratio > 5 ? 'critique' : 'contaminé';
                } else {
                    $statut = 'dégradé';
                }
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
                'recommandation'  => $this->recommandationSol($parametre, $conforme, $statut),
            ];
        }

        $nonConformes = array_filter($comparaisons, fn($c) => !$c['conforme']);
        $critiques    = array_filter($nonConformes, fn($c)  => $c['est_critique']);

        return [
            'type'                  => 'sol',
            'source_locale'         => 'EcoDash — Analyse pédologique terrain',
            'source_reference'      => 'FAO VGSSM 2017 + Directives UE',
            'date_analyse'          => $mesureLocale['date_prelevement'] ?? now()->toISOString(),
            'site'                  => $mesureLocale['site']    ?? null,
            'site_id'               => $mesureLocale['site_id'] ?? null,
            'comparaisons'          => $comparaisons,
            'nb_non_conformes'      => count($nonConformes),
            'contaminants_critiques'=> array_keys($critiques),
            'conformite_globale'    => count($nonConformes) === 0,
            'statut_global'         => count($critiques) > 0 ? 'critique' : (count($nonConformes) > 0 ? 'dégradé' : 'sain'),
            'iqs_local'             => $mesureLocale['indice_qualite_sol'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────
    private function facteurConversion(string $property): array
    {
        return match ($property) {
            'phh2o'    => ['mult' => 0.1,  'add' => 0, 'unite' => 'pH'],
            'soc'      => ['mult' => 0.1,  'add' => 0, 'unite' => 'g/kg'],
            'nitrogen' => ['mult' => 0.01, 'add' => 0, 'unite' => 'g/kg'],
            'bdod'     => ['mult' => 0.01, 'add' => 0, 'unite' => 'kg/dm³'],
            'cec'      => ['mult' => 0.1,  'add' => 0, 'unite' => 'mmol(c)/kg'],
            'cfvo'     => ['mult' => 0.1,  'add' => 0, 'unite' => 'cm³/dm³'],
            default    => ['mult' => 1.0,  'add' => 0, 'unite' => '%'],
        };
    }

    private function labelISRIC(string $property): string
    {
        return match ($property) {
            'phh2o'    => 'pH sol (eau)',
            'soc'      => 'Carbone organique',
            'nitrogen' => 'Azote total',
            'clay'     => 'Argile',
            'sand'     => 'Sable',
            'silt'     => 'Limon',
            'bdod'     => 'Densité apparente',
            'cec'      => 'Capacité d\'échange cationique',
            'cfvo'     => 'Fragments grossiers',
            default    => $property,
        };
    }

    private function recommandationSol(string $param, bool $conforme, string $statut): string
    {
        if ($conforme) return 'Valeur conforme aux normes FAO.';
        $messages = [
            'plomb_sol'            => '⚠ CONTAMINATION PLOMB — Interdire cultures alimentaires. Phytoremédiation ou excavation requise.',
            'hydrocarbures_totaux' => '⚠ POLLUTION HYDROCARBURES — Bioremédiation urgente. Source probable : fuite pipeline ou déversement.',
            'arsenic_sol'          => '⚠ ARSENIC — Contamination naturelle ou minière. Analyses approfondies et restrictions agricoles.',
            'cadmium_sol'          => '⚠ CADMIUM — Interdit pour cultures maraîchères. Source engrais phosphatés ou industrie.',
            'mercure_sol'          => '⚠ MERCURE — Contamination grave. Source probable : orpaillage ou industrie. Dépollution requise.',
            'chrome_sol'           => '⚠ CHROME — Source industrielle probable. Analyses Cr(VI) urgentes — cancérigène.',
            'pcb_totaux'           => '⚠ PCB — Polluants organiques persistants. Dépollution longue et coûteuse. Isoler la zone.',
            'ph_sol'               => 'pH hors plage optimale — Amendement calcaire (si acide) ou soufre (si alcalin) recommandé.',
            'matiere_organique'    => 'Matière organique insuffisante — Apport de compost, rotation cultures, couverts végétaux.',
        ];
        return $messages[$param] ?? "Sol {$statut} pour {$param} — intervention agronomique recommandée.";
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
