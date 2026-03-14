<?php

namespace App\Services\International;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service — Indicateurs Sociaux & ESG (Données Internationales)
 * Sources : World Bank (indicateurs sociaux), UNDP (IDH et développement humain)
 */
class SocialESGService
{
    // ─────────────────────────────────────────────────────────────────
    // World Bank — Indicateurs de développement
    // API : https://api.worldbank.org/v2/
    // Pas de clé requise
    // ─────────────────────────────────────────────────────────────────

    // Indicateurs pertinents pour l'ESG industriel
    const INDICATEURS_WB = [
        // Eau et assainissement
        'SH.H2O.BASW.ZS'   => ['label' => 'Accès eau potable de base (%)',           'module' => 'eau'],
        'SH.STA.BASS.ZS'   => ['label' => 'Accès services assainissement de base (%)', 'module' => 'eau'],
        // Environnement
        'EN.ATM.CO2E.KT'   => ['label' => 'Émissions CO₂ (kt)',                       'module' => 'air'],
        'EN.ATM.PM25.MC.M3' => ['label' => 'Exposition PM2.5 (µg/m³ moyen)',           'module' => 'air'],
        'AG.LND.FRST.ZS'   => ['label' => 'Superficie forêts (% terres)',             'module' => 'biodiversite'],
        // Social
        'SI.POV.NAHC'      => ['label' => 'Taux pauvreté national (%)',               'module' => 'social'],
        'SL.UEM.TOTL.ZS'   => ['label' => 'Taux chômage (%)',                         'module' => 'emploi'],
        'SP.POP.TOTL'       => ['label' => 'Population totale',                        'module' => 'social'],
        'SP.RUR.TOTL.ZS'   => ['label' => 'Population rurale (%)',                    'module' => 'social'],
        'SE.ADT.LITR.ZS'   => ['label' => 'Taux d\'alphabétisation adulte (%)',       'module' => 'social'],
        'SH.DYN.MORT'      => ['label' => 'Mortalité infantile (pour 1000)',           'module' => 'sante'],
        // Économie
        'NY.GDP.PCAP.CD'   => ['label' => 'PIB par habitant (USD)',                   'module' => 'social'],
    ];

    // Indicateurs pays depuis World Bank
    public function getIndicateursPays(string $pays = 'CD', ?int $annee = null): array
    {
        $annee    = $annee ?? (now()->year - 2); // données avec 2 ans de décalage
        $cacheKey = 'wb_indicateurs_' . $pays . '_' . $annee;

        return Cache::remember($cacheKey, now()->addDays(1), function () use ($pays, $annee) {
            $indicateurs = array_keys(self::INDICATEURS_WB);
            $codes       = implode(';', $indicateurs);

            try {
                $response = Http::timeout(20)->get(
                    "https://api.worldbank.org/v2/country/{$pays}/indicator/{$codes}", [
                    'date'     => $annee,
                    'format'   => 'json',
                    'per_page' => 50,
                ]);

                if ($response->successful()) {
                    $data = $response->json() ?? [];
                    $meta = $data[0] ?? [];
                    $rows = $data[1] ?? [];

                    $resultats = [];
                    foreach ($rows as $row) {
                        $code = $row['indicator']['id'] ?? null;
                        if (!$code) continue;
                        $infos = self::INDICATEURS_WB[$code] ?? [];
                        $resultats[$code] = [
                            'label'   => $infos['label']  ?? $row['indicator']['value'] ?? $code,
                            'module'  => $infos['module'] ?? null,
                            'valeur'  => $row['value'],
                            'annee'   => $row['date'],
                            'pays'    => $row['country']['value'] ?? null,
                        ];
                    }

                    return [
                        'source'      => 'World Bank — World Development Indicators',
                        'pays'        => $pays,
                        'annee'       => $annee,
                        'disponible'  => true,
                        'indicateurs' => $resultats,
                        'meta'        => ['total' => $meta['total'] ?? count($rows)],
                    ];
                }
                return $this->indisponible('World Bank', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('World Bank API error: ' . $e->getMessage());
                return $this->indisponible('World Bank', $e->getMessage());
            }
        });
    }

    // Indicateur spécifique World Bank avec historique
    public function getHistoriqueIndicateur(string $pays = 'CD', string $indicateur = 'EN.ATM.PM25.MC.M3', int $annees = 10): array
    {
        $cacheKey = 'wb_historique_' . md5("$pays,$indicateur,$annees");
        return Cache::remember($cacheKey, now()->addHours(12), function () use ($pays, $indicateur, $annees) {
            try {
                $dateRange = (now()->year - $annees) . ':' . now()->year;
                $response  = Http::timeout(15)->get(
                    "https://api.worldbank.org/v2/country/{$pays}/indicator/{$indicateur}", [
                    'date'     => $dateRange,
                    'format'   => 'json',
                    'per_page' => 20,
                ]);

                if ($response->successful()) {
                    $rows = $response->json('1') ?? [];
                    $serie = collect($rows)
                        ->filter(fn($r) => $r['value'] !== null)
                        ->sortBy('date')
                        ->map(fn($r) => ['annee' => $r['date'], 'valeur' => $r['value']])
                        ->values();

                    $infos = self::INDICATEURS_WB[$indicateur] ?? [];

                    return [
                        'source'      => 'World Bank',
                        'pays'        => $pays,
                        'indicateur'  => $indicateur,
                        'label'       => $infos['label'] ?? $rows[0]['indicator']['value'] ?? $indicateur,
                        'disponible'  => true,
                        'serie'       => $serie->toArray(),
                        'derniere_valeur' => $serie->last()['valeur'] ?? null,
                        'tendance'    => $this->calculerTendance($serie->pluck('valeur')->toArray()),
                    ];
                }
                return $this->indisponible('World Bank', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('World Bank historique error: ' . $e->getMessage());
                return $this->indisponible('World Bank', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // UNDP — Human Development Index
    // API : https://hdr.undp.org/sites/default/files/2021-22_HDR/HDR21-22_Statistical_Annex_HDI_Table.xlsx
    // Note : L'API REST UNDP est limitée — utilisons l'API HDR
    // ─────────────────────────────────────────────────────────────────
    public function getDonneesIDH(string $pays = 'COD'): array
    {
        $cacheKey = 'undp_idh_' . $pays;
        return Cache::remember($cacheKey, now()->addDays(1), function () use ($pays) {
            try {
                // API HDR officielle UNDP
                $response = Http::timeout(15)->get('https://hdr.undp.org/system/files/2023/07/hdro_api_country_year_cty_year_all_2023.json');

                if ($response->successful()) {
                    $data = $response->json('indicators') ?? [];
                    // Chercher IDH pour le pays
                    $idh = collect($data)->filter(fn($item) =>
                        ($item['iso3'] ?? '') === $pays && ($item['indicator_id'] ?? '') === '137506'
                    )->sortByDesc('year')->first();

                    return [
                        'source'      => 'UNDP — Human Development Report Office',
                        'pays'        => $pays,
                        'disponible'  => true,
                        'idh'         => $idh ? [
                            'valeur'       => $idh['value']     ?? null,
                            'annee'        => $idh['year']      ?? null,
                            'categorie'    => $this->categorieIDH($idh['value'] ?? 0),
                            'rang_mondial' => null, // nécessite appel supplémentaire
                        ] : null,
                        'note' => 'IDH : Développement humain très faible (<0.55), Faible (0.55-0.7), Moyen (0.7-0.8), Élevé (>0.8)',
                    ];
                }

                // Fallback : appel API UNDP v1
                $r2 = Http::timeout(10)->get("https://api.undp.org/v1/sdg_push_diagnostics/country/{$pays}");
                if ($r2->successful()) {
                    return [
                        'source'     => 'UNDP — SDG Push Diagnostics',
                        'pays'       => $pays,
                        'disponible' => true,
                        'data'       => $r2->json(),
                    ];
                }

                return $this->indisponible('UNDP', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('UNDP API error: ' . $e->getMessage());
                return $this->indisponible('UNDP', $e->getMessage());
            }
        });
    }

    // UNDP — ODD (Objectifs de Développement Durable) par pays
    public function getODDPays(string $pays = 'COD'): array
    {
        $cacheKey = 'undp_odd_' . $pays;
        return Cache::remember($cacheKey, now()->addDays(3), function () use ($pays) {
            try {
                $response = Http::timeout(15)->get("https://unstats.un.org/SDGAPI/v1/sdg/DataAvailability/CountrySeries", [
                    'areaCode' => $pays,
                    'pageSize' => 20,
                ]);

                if ($response->successful()) {
                    return [
                        'source'      => 'UN Stats — SDG Indicators Database',
                        'pays'        => $pays,
                        'disponible'  => true,
                        'data'        => $response->json(),
                        'odd_pertinents_esg' => [
                            'ODD 3'  => 'Bonne santé et bien-être',
                            'ODD 6'  => 'Eau propre et assainissement',
                            'ODD 7'  => 'Énergie propre',
                            'ODD 8'  => 'Travail décent et croissance économique',
                            'ODD 13' => 'Lutte contre les changements climatiques',
                            'ODD 14' => 'Vie aquatique',
                            'ODD 15' => 'Vie terrestre — biodiversité',
                        ],
                    ];
                }
                return $this->indisponible('UN Stats', 'HTTP ' . $response->status());
            } catch (\Exception $e) {
                Log::warning('UNDP ODD error: ' . $e->getMessage());
                return $this->indisponible('UN Stats', $e->getMessage());
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Comparaison indicateurs emploi locaux vs World Bank pays
    // ─────────────────────────────────────────────────────────────────
    public function comparerIndicateursEmploi(array $indicateursLocaux, string $pays = 'CD'): array
    {
        $wbData = $this->getIndicateursPays($pays);
        $wb     = $wbData['indicateurs'] ?? [];

        $txChomageNational = $wb['SL.UEM.TOTL.ZS']['valeur'] ?? null;
        $txPauvreteNational = $wb['SI.POV.NAHC']['valeur']   ?? null;

        $txEmploiLocal = $indicateursLocaux['emplois_locaux_pct']    ?? null;
        $salaireMoyen  = $indicateursLocaux['salaire_moyen']         ?? null;

        $comparaisons = [];

        if ($txChomageNational !== null && $txEmploiLocal !== null) {
            $txChomageLocal = 100 - (float) $txEmploiLocal;
            $comparaisons['chomage'] = [
                'valeur_locale'    => round($txChomageLocal, 1),
                'valeur_nationale' => round($txChomageNational, 1),
                'unite'            => '%',
                'ecart'            => round($txChomageLocal - $txChomageNational, 1),
                'impact'           => $txChomageLocal < $txChomageNational ? 'positif' : 'négatif',
                'interpretation'   => $txChomageLocal < $txChomageNational
                    ? "✓ Le projet contribue à réduire le chômage local (−{$txChomageNational}% national)"
                    : "⚠ Chômage local supérieur à la moyenne nationale",
            ];
        }

        return [
            'source_locale'    => 'EcoDash — Données RH et emploi locales',
            'source_reference' => 'World Bank — World Development Indicators',
            'pays'             => $pays,
            'comparaisons'     => $comparaisons,
            'contexte_national'=> [
                'taux_chomage'   => $txChomageNational,
                'taux_pauvrete'  => $txPauvreteNational,
                'pib_habitant'   => $wb['NY.GDP.PCAP.CD']['valeur'] ?? null,
                'acces_eau'      => $wb['SH.H2O.BASW.ZS']['valeur'] ?? null,
                'source'         => $wbData['source'] ?? 'World Bank',
                'annee'          => $wbData['annee']  ?? null,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────
    private function categorieIDH(float $valeur): string
    {
        if ($valeur >= 0.800) return 'Développement humain très élevé';
        if ($valeur >= 0.700) return 'Développement humain élevé';
        if ($valeur >= 0.550) return 'Développement humain moyen';
        return 'Développement humain faible';
    }

    private function calculerTendance(array $valeurs): string
    {
        $valeurs = array_filter($valeurs, fn($v) => $v !== null);
        if (count($valeurs) < 2) return 'insuffisant';
        $premier = reset($valeurs);
        $dernier = end($valeurs);
        $delta   = $dernier - $premier;
        if (abs($delta) < 0.01 * abs($premier)) return 'stable';
        return $delta > 0 ? 'hausse' : 'baisse';
    }

    private function indisponible(string $source, string $raison): array
    {
        return ['source' => $source, 'disponible' => false, 'raison' => $raison];
    }
}
