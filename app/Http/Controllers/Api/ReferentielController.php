<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\International\AirQualityService;
use App\Services\International\WaterQualityService;
use App\Services\International\SoilQualityService;
use App\Services\International\BiodiversityService;
use App\Services\International\SatelliteService;
use App\Services\International\SocialESGService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReferentielController
 * ─────────────────────────────────────────────────────────────────
 * Expose les données et normes des organisations internationales.
 * Ces endpoints sont utilisés pour afficher les références dans le
 * dashboard et pour la comparaison avec les mesures terrain.
 *
 * Toutes les routes sont protégées par auth:sanctum
 */
class ReferentielController extends Controller
{
    public function __construct(
        private AirQualityService  $airService,
        private WaterQualityService $waterService,
        private SoilQualityService  $soilService,
        private BiodiversityService $bioService,
        private SatelliteService    $satelliteService,
        private SocialESGService    $socialService,
    ) {}

    // ════════════════════════════════════════════════════════════════
    // NORMES OMS / FAO / IUCN (données de référence statiques)
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/normes
     * Retourne toutes les normes internationales en une seule requête
     */
    public function toutesNormes(): JsonResponse
    {
        return response()->json([
            'data' => [
                'air'          => $this->airService->getNormesOMS(),
                'eau'          => $this->waterService->getNormesOMS(),
                'sol'          => $this->soilService->getNormesFAO(),
                'biodiversite' => $this->bioService->getStatutsIUCN(),
            ],
            'meta' => [
                'description' => 'Normes internationales OMS / FAO / IUCN intégrées dans EcoDash',
                'mise_a_jour' => now()->toISOString(),
            ],
        ]);
    }

    /** GET /api/referentiel/normes/air */
    public function normesAir(): JsonResponse
    {
        return response()->json(['data' => $this->airService->getNormesOMS()]);
    }

    /** GET /api/referentiel/normes/eau */
    public function normesEau(): JsonResponse
    {
        return response()->json(['data' => $this->waterService->getNormesOMS()]);
    }

    /** GET /api/referentiel/normes/sol */
    public function normesSol(): JsonResponse
    {
        return response()->json(['data' => $this->soilService->getNormesFAO()]);
    }

    /** GET /api/referentiel/normes/biodiversite */
    public function normesBiodiversite(): JsonResponse
    {
        return response()->json(['data' => $this->bioService->getStatutsIUCN()]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — AIR
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/air/iqair?ville=Kinshasa&pays=Congo
     * Données IQAir temps réel par ville
     */
    public function airIQAir(Request $request): JsonResponse
    {
        $request->validate([
            'ville' => 'required|string|max:100',
            'pays'  => 'nullable|string|max:100',
            'etat'  => 'nullable|string|max:100',
        ]);

        return response()->json([
            'data' => $this->airService->getDonneesIQAir(
                $request->ville,
                $request->etat ?? '',
                $request->pays ?? 'Congo'
            ),
        ]);
    }

    /**
     * GET /api/referentiel/air/openweather?lat=-4.32&lon=15.32
     * Données OpenWeather Air Pollution par coordonnées
     */
    public function airOpenWeather(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return response()->json([
            'data' => $this->airService->getDonneesOpenWeather(
                (float) $request->lat,
                (float) $request->lon
            ),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — EAU
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/eau/usgs?site=09380000
     * Données USGS rivières par code station
     */
    public function eauUSGS(Request $request): JsonResponse
    {
        $request->validate(['site' => 'nullable|string|max:20']);
        return response()->json([
            'data' => $this->waterService->getDonneesUSGS($request->site ?? '09380000'),
        ]);
    }

    /**
     * GET /api/referentiel/eau/wri?pays=COD
     * Données WRI stress hydrique
     */
    public function eauWRI(Request $request): JsonResponse
    {
        $request->validate(['pays' => 'nullable|string|max:5']);
        return response()->json([
            'data' => $this->waterService->getDonneesWRI($request->pays ?? 'COD'),
        ]);
    }

    /**
     * GET /api/referentiel/eau/noaa?station=8518750
     * Données NOAA températures marines
     */
    public function eauNOAA(Request $request): JsonResponse
    {
        $request->validate(['station' => 'nullable|string|max:20']);
        return response()->json([
            'data' => $this->waterService->getDonneesNOAA($request->station ?? '8518750'),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — SOL
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/sol/isric?lat=-4.3&lon=15.3
     * Données ISRIC SoilGrids par coordonnées
     */
    public function solISRIC(Request $request): JsonResponse
    {
        $request->validate([
            'lat'       => 'required|numeric|between:-90,90',
            'lon'       => 'required|numeric|between:-180,180',
            'profondeur'=> 'nullable|string|in:0-5cm,5-15cm,15-30cm,30-60cm',
        ]);

        return response()->json([
            'data' => $this->soilService->getDonneesISRIC(
                (float) $request->lat,
                (float) $request->lon,
                $request->profondeur ?? '0-5cm'
            ),
        ]);
    }

    /**
     * GET /api/referentiel/sol/fao?q=soil contamination africa
     * Jeux de données FAO sur les sols
     */
    public function solFAO(Request $request): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:200']);
        return response()->json([
            'data' => $this->soilService->getDonneesFAO($request->q ?? 'soil contamination africa'),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — BIODIVERSITÉ
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/biodiversite/gbif?lat=-4.3&lon=15.3&rayon=50
     * Occurrences d'espèces GBIF autour d'un point
     */
    public function biodiversiteGBIF(Request $request): JsonResponse
    {
        $request->validate([
            'lat'   => 'required|numeric|between:-90,90',
            'lon'   => 'required|numeric|between:-180,180',
            'rayon' => 'nullable|numeric|min:1|max:500',
        ]);

        return response()->json([
            'data' => $this->bioService->getOccurrencesGBIF(
                (float) $request->lat,
                (float) $request->lon,
                (float) ($request->rayon ?? 50)
            ),
        ]);
    }

    /**
     * GET /api/referentiel/biodiversite/gbif/recherche?nom=Pan troglodytes
     * Recherche d'espèce dans GBIF
     */
    public function rechercherEspece(Request $request): JsonResponse
    {
        $request->validate(['nom' => 'required|string|max:200']);
        return response()->json([
            'data' => $this->bioService->rechercherEspeceGBIF($request->nom),
        ]);
    }

    /**
     * GET /api/referentiel/biodiversite/iucn?region=africa
     * Espèces menacées IUCN par région
     */
    public function biodiversiteIUCN(Request $request): JsonResponse
    {
        $request->validate(['region' => 'nullable|string|max:50']);
        return response()->json([
            'data' => $this->bioService->getEspecesMenaceesIUCN($request->region ?? 'africa'),
        ]);
    }

    /**
     * GET /api/referentiel/biodiversite/iucn/espece?nom=Gorilla gorilla
     * Statut IUCN d'une espèce par nom scientifique
     */
    public function statutEspece(Request $request): JsonResponse
    {
        $request->validate(['nom' => 'required|string|max:200']);
        return response()->json([
            'data' => $this->bioService->getStatutEspeceIUCN($request->nom),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — SATELLITES
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/satellite/incendies?lat=-4.3&lon=15.3&rayon=100
     * Incendies actifs NASA FIRMS 72h
     */
    public function satelliteIncendies(Request $request): JsonResponse
    {
        $request->validate([
            'lat'   => 'required|numeric|between:-90,90',
            'lon'   => 'required|numeric|between:-180,180',
            'rayon' => 'nullable|numeric|min:10|max:500',
        ]);

        return response()->json([
            'data' => $this->satelliteService->getIncendiesNASA(
                (float) $request->lat,
                (float) $request->lon,
                (float) ($request->rayon ?? 100)
            ),
        ]);
    }

    /**
     * GET /api/referentiel/satellite/meteo?lat=-4.3&lon=15.3
     * Données météo historiques NASA POWER
     */
    public function satelliteMeteo(Request $request): JsonResponse
    {
        $request->validate([
            'lat'   => 'required|numeric|between:-90,90',
            'lon'   => 'required|numeric|between:-180,180',
            'debut' => 'nullable|date_format:Ymd',
            'fin'   => 'nullable|date_format:Ymd',
        ]);

        return response()->json([
            'data' => $this->satelliteService->getDonneesMeteoNASA(
                (float) $request->lat,
                (float) $request->lon,
                $request->debut,
                $request->fin
            ),
        ]);
    }

    /**
     * GET /api/referentiel/satellite/evenements?jours=30&categorie=wildfires
     * Événements naturels NASA EONET
     */
    public function satelliteEvenements(Request $request): JsonResponse
    {
        $request->validate([
            'jours'     => 'nullable|integer|min:1|max:365',
            'categorie' => 'nullable|string|max:50',
        ]);

        return response()->json([
            'data' => $this->satelliteService->getEvenementsNaturelsNASA(
                (int) ($request->jours ?? 30),
                $request->categorie ?? ''
            ),
        ]);
    }

    /**
     * GET /api/referentiel/satellite/esa?lat=-4.3&lon=15.3&collection=SENTINEL-2
     * Catalogue images ESA Copernicus
     */
    public function satelliteESA(Request $request): JsonResponse
    {
        $request->validate([
            'lat'        => 'required|numeric|between:-90,90',
            'lon'        => 'required|numeric|between:-180,180',
            'collection' => 'nullable|string|in:SENTINEL-1,SENTINEL-2,SENTINEL-3,SENTINEL-5P',
        ]);

        return response()->json([
            'data' => $this->satelliteService->getCatalogueESA(
                (float) $request->lat,
                (float) $request->lon,
                $request->collection ?? 'SENTINEL-2'
            ),
        ]);
    }

    /**
     * GET /api/referentiel/satellite/deforestation?lat=-4.3&lon=15.3
     * Surveillance déforestation combinée NASA + ESA
     */
    public function deforestation(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        return response()->json([
            'data' => $this->satelliteService->getSurveillanceDeforestation(
                (float) $request->lat,
                (float) $request->lon
            ),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    // DONNÉES RÉELLES — SOCIAL / ESG
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /api/referentiel/social/worldbank?pays=CD&annee=2022
     * Indicateurs World Bank par pays
     */
    public function socialWorldBank(Request $request): JsonResponse
    {
        $request->validate([
            'pays'  => 'nullable|string|max:5',
            'annee' => 'nullable|integer|min:2000|max:2024',
        ]);

        return response()->json([
            'data' => $this->socialService->getIndicateursPays(
                $request->pays ?? 'CD',
                $request->annee
            ),
        ]);
    }

    /**
     * GET /api/referentiel/social/worldbank/historique?pays=CD&indicateur=EN.ATM.PM25.MC.M3
     * Historique d'un indicateur World Bank
     */
    public function historiqueIndicateur(Request $request): JsonResponse
    {
        $request->validate([
            'pays'       => 'nullable|string|max:5',
            'indicateur' => 'required|string|max:50',
            'annees'     => 'nullable|integer|min:1|max:30',
        ]);

        return response()->json([
            'data' => $this->socialService->getHistoriqueIndicateur(
                $request->pays  ?? 'CD',
                $request->indicateur,
                (int) ($request->annees ?? 10)
            ),
        ]);
    }

    /**
     * GET /api/referentiel/social/undp?pays=COD
     * IDH et indicateurs UNDP
     */
    public function socialUNDP(Request $request): JsonResponse
    {
        $request->validate(['pays' => 'nullable|string|max:5']);
        return response()->json([
            'data' => $this->socialService->getDonneesIDH($request->pays ?? 'COD'),
        ]);
    }

    /**
     * GET /api/referentiel/social/odd?pays=COD
     * Objectifs de Développement Durable (ODD/SDG)
     */
    public function socialODD(Request $request): JsonResponse
    {
        $request->validate(['pays' => 'nullable|string|max:5']);
        return response()->json([
            'data' => $this->socialService->getODDPays($request->pays ?? 'COD'),
        ]);
    }
}
