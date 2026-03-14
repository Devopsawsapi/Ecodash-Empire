<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\International\AirQualityService;
use App\Services\International\WaterQualityService;
use App\Services\International\SoilQualityService;
use App\Services\International\BiodiversityService;
use App\Services\International\SocialESGService;
use App\Models\MesureAir;
use App\Models\MesureEau;
use App\Models\MesureSol;
use App\Models\ObservationBiodiversite;
use App\Models\IndicateurEmploi;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ComparaisonController
 * Compare les mesures terrain EcoDash avec les normes et données internationales.
 */
class ComparaisonController extends Controller
{
    public function __construct(
        private AirQualityService   $airService,
        private WaterQualityService $waterService,
        private SoilQualityService  $soilService,
        private BiodiversityService $bioService,
        private SocialESGService    $socialService,
    ) {}

    // ── AIR ───────────────────────────────────────────────────────

    /** GET /api/comparaison/air/{id} — Comparaison mesure air vs OMS */
    public function comparerAir(int $id): JsonResponse
    {
        $mesure  = MesureAir::with('site')->findOrFail($id);
        $vsOMS   = $this->airService->comparerAvecNormes(array_merge($mesure->toArray(), ['site' => $mesure->site?->nom]));
        $vsOW    = null;
        $vsIQAir = null;

        if ($mesure->site?->latitude && $mesure->site?->longitude) {
            $vsOW = $this->airService->getDonneesOpenWeather((float)$mesure->site->latitude, (float)$mesure->site->longitude);
        }
        if ($mesure->site?->ville) {
            $vsIQAir = $this->airService->getDonneesIQAir($mesure->site->ville, '', $mesure->site->pays ?? 'Congo');
        }

        return response()->json([
            'mesure_locale'       => $this->resumeAir($mesure),
            'vs_normes_oms'       => $vsOMS,
            'vs_iqair_temps_reel' => $vsIQAir,
            'vs_openweather'      => $vsOW,
            'synthese'            => $this->syntheseAir($vsOMS, $vsIQAir, $vsOW),
        ]);
    }

    /** POST /api/comparaison/air/analyse — Analyse à la volée */
    public function analyserAir(Request $request): JsonResponse
    {
        $request->validate([
            'pm25' => 'nullable|numeric|min:0',
            'pm10' => 'nullable|numeric|min:0',
            'no2'  => 'nullable|numeric|min:0',
            'so2'  => 'nullable|numeric|min:0',
            'o3'   => 'nullable|numeric|min:0',
            'co'   => 'nullable|numeric|min:0',
            'lat'  => 'nullable|numeric|between:-90,90',
            'lon'  => 'nullable|numeric|between:-180,180',
        ]);
        $vsOMS = $this->airService->comparerAvecNormes($request->all());
        $vsOW  = null;
        if ($request->lat && $request->lon) {
            $vsOW = $this->airService->getDonneesOpenWeather((float)$request->lat, (float)$request->lon);
        }
        return response()->json(['vs_normes_oms' => $vsOMS, 'vs_openweather' => $vsOW, 'synthese' => $this->syntheseAir($vsOMS, null, $vsOW)]);
    }

    // ── EAU ───────────────────────────────────────────────────────

    /** GET /api/comparaison/eau/{id} — Comparaison mesure eau vs OMS */
    public function comparerEau(int $id): JsonResponse
    {
        $mesure  = MesureEau::with('site')->findOrFail($id);
        $vsOMS   = $this->waterService->comparerAvecNormes(array_merge($mesure->toArray(), ['site' => $mesure->site?->nom]));
        $usgsRef = $this->waterService->getDonneesUSGS();

        return response()->json([
            'mesure_locale'  => $this->resumeEau($mesure),
            'vs_normes_oms'  => $vsOMS,
            'reference_usgs' => $usgsRef,
            'synthese'       => $this->syntheseEau($vsOMS),
        ]);
    }

    /** POST /api/comparaison/eau/analyse — Analyse à la volée */
    public function analyserEau(Request $request): JsonResponse
    {
        $request->validate([
            'ph'              => 'nullable|numeric|between:0,14',
            'turbidite'       => 'nullable|numeric|min:0',
            'oxygene_dissous' => 'nullable|numeric|min:0',
            'plomb'           => 'nullable|numeric|min:0',
            'mercure'         => 'nullable|numeric|min:0',
            'arsenic'         => 'nullable|numeric|min:0',
            'cadmium'         => 'nullable|numeric|min:0',
            'e_coli'          => 'nullable|numeric|min:0',
            'nitrates'        => 'nullable|numeric|min:0',
        ]);
        $vsOMS = $this->waterService->comparerAvecNormes($request->all());
        return response()->json(['vs_normes_oms' => $vsOMS, 'synthese' => $this->syntheseEau($vsOMS)]);
    }

    // ── SOL ───────────────────────────────────────────────────────

    /** GET /api/comparaison/sol/{id} — Comparaison mesure sol vs FAO */
    public function comparerSol(int $id): JsonResponse
    {
        $mesure   = MesureSol::with('site')->findOrFail($id);
        $vsFAO    = $this->soilService->comparerAvecNormes(array_merge($mesure->toArray(), ['site' => $mesure->site?->nom]));
        $isricRef = null;

        if ($mesure->site?->latitude && $mesure->site?->longitude) {
            $isricRef = $this->soilService->getDonneesISRIC((float)$mesure->site->latitude, (float)$mesure->site->longitude);
        }

        return response()->json([
            'mesure_locale'  => $this->resumeSol($mesure),
            'vs_normes_fao'  => $vsFAO,
            'reference_isric'=> $isricRef,
            'synthese'       => $this->syntheseSol($vsFAO),
        ]);
    }

    /** POST /api/comparaison/sol/analyse — Analyse à la volée */
    public function analyserSol(Request $request): JsonResponse
    {
        $request->validate([
            'ph_sol'               => 'nullable|numeric|between:0,14',
            'matiere_organique'    => 'nullable|numeric|min:0',
            'plomb_sol'            => 'nullable|numeric|min:0',
            'arsenic_sol'          => 'nullable|numeric|min:0',
            'mercure_sol'          => 'nullable|numeric|min:0',
            'hydrocarbures_totaux' => 'nullable|numeric|min:0',
        ]);
        $vsFAO = $this->soilService->comparerAvecNormes($request->all());
        return response()->json(['vs_normes_fao' => $vsFAO, 'synthese' => $this->syntheseSol($vsFAO)]);
    }

    // ── BIODIVERSITÉ ──────────────────────────────────────────────

    /** GET /api/comparaison/biodiversite/{id} — Observation vs IUCN */
    public function comparerBiodiversite(int $id): JsonResponse
    {
        $obs  = ObservationBiodiversite::with('site')->findOrFail($id);
        $vsIUCN = $this->bioService->comparerObservationLocale($obs->toArray());
        $gbif = null;
        if ($obs->latitude_obs && $obs->longitude_obs) {
            $gbif = $this->bioService->getOccurrencesGBIF((float)$obs->latitude_obs, (float)$obs->longitude_obs, 20);
        }
        return response()->json([
            'observation_locale' => ['id' => $obs->id, 'espece' => $obs->nom_espece, 'nom_scientifique' => $obs->nom_scientifique, 'statut_local' => $obs->statut_iucn],
            'vs_iucn'            => $vsIUCN,
            'occurrences_gbif'   => $gbif,
        ]);
    }

    // ── SOCIAL ────────────────────────────────────────────────────

    /** GET /api/comparaison/social?pays=CD */
    public function comparerSocial(Request $request): JsonResponse
    {
        $pays     = $request->pays ?? 'CD';
        $emploi   = IndicateurEmploi::latest()->first();
        $comp     = $this->socialService->comparerIndicateursEmploi($emploi ? $emploi->toArray() : [], $pays);
        $idh      = $this->socialService->getDonneesIDH($pays === 'CD' ? 'COD' : $pays);
        $wb       = $this->socialService->getIndicateursPays($pays);

        return response()->json([
            'donnees_locales' => $emploi?->only(['id', 'emplois_locaux', 'emplois_totaux', 'salaire_moyen', 'femmes_pct', 'periode']),
            'vs_worldbank'    => $comp,
            'idh_undp'        => $idh,
            'indicateurs_wb'  => $wb,
        ]);
    }

    // ── RAPPORT SITE GLOBAL ───────────────────────────────────────

    /** GET /api/comparaison/site/{id} — Rapport complet conformité internationale */
    public function rapportSite(int $siteId): JsonResponse
    {
        $site     = Site::findOrFail($siteId);
        $air      = MesureAir::where('site_id', $siteId)->latest('date_mesure')->first();
        $eau      = MesureEau::where('site_id', $siteId)->latest('date_prelevement')->first();
        $sol      = MesureSol::where('site_id', $siteId)->latest('date_prelevement')->first();

        $cAir = $air ? $this->airService->comparerAvecNormes(array_merge($air->toArray(),   ['site' => $site->nom])) : null;
        $cEau = $eau ? $this->waterService->comparerAvecNormes(array_merge($eau->toArray(), ['site' => $site->nom])) : null;
        $cSol = $sol ? $this->soilService->comparerAvecNormes(array_merge($sol->toArray(),  ['site' => $site->nom])) : null;

        $scores = [];
        if ($cAir) $scores['air'] = $cAir['conformite_globale'] ? 100 : max(0, 100 - ($cAir['nb_non_conformes'] ?? 0) * 20);
        if ($cEau) $scores['eau'] = $cEau['conformite_globale'] ? 100 : max(0, 100 - ($cEau['nb_non_conformes'] ?? 0) * 15);
        if ($cSol) $scores['sol'] = $cSol['conformite_globale'] ? 100 : max(0, 100 - ($cSol['nb_non_conformes'] ?? 0) * 15);
        $moy = count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;

        return response()->json([
            'site'           => ['id' => $site->id, 'nom' => $site->nom, 'type' => $site->type_site ?? null],
            'conformite_air' => $cAir,
            'conformite_eau' => $cEau,
            'conformite_sol' => $cSol,
            'score_global'   => [
                'par_module' => $scores,
                'moyen'      => $moy,
                'niveau'     => $moy === null ? 'inconnu' : ($moy >= 80 ? 'conforme' : ($moy >= 50 ? 'attention' : 'critique')),
            ],
            'synthese_air'   => $cAir ? $this->syntheseAir($cAir, null, null) : null,
            'synthese_eau'   => $cEau ? $this->syntheseEau($cEau) : null,
            'synthese_sol'   => $cSol ? $this->syntheseSol($cSol) : null,
            'genere_le'      => now()->toISOString(),
        ]);
    }

    // ── Helpers résumés et synthèses ──────────────────────────────

    private function resumeAir(MesureAir $m): array
    {
        return ['id' => $m->id, 'site' => $m->site?->nom, 'date' => $m->date_mesure, 'pm25' => $m->pm25, 'pm10' => $m->pm10, 'no2' => $m->no2, 'so2' => $m->so2, 'o3' => $m->o3, 'co' => $m->co, 'iqa' => $m->iqa, 'statut' => $m->statut_global];
    }

    private function resumeEau(MesureEau $m): array
    {
        return ['id' => $m->id, 'site' => $m->site?->nom, 'date' => $m->date_prelevement, 'ph' => $m->ph, 'turbidite' => $m->turbidite, 'oxygene_dissous' => $m->oxygene_dissous, 'plomb' => $m->plomb, 'mercure' => $m->mercure, 'arsenic' => $m->arsenic, 'e_coli' => $m->e_coli, 'indice_qualite' => $m->indice_qualite, 'statut' => $m->statut_global, 'anomalies' => $m->anomalies];
    }

    private function resumeSol(MesureSol $m): array
    {
        return ['id' => $m->id, 'site' => $m->site?->nom, 'date' => $m->date_prelevement, 'ph_sol' => $m->ph_sol, 'matiere_organique' => $m->matiere_organique, 'plomb_sol' => $m->plomb_sol, 'hydrocarbures_totaux' => $m->hydrocarbures_totaux, 'indice_qualite_sol' => $m->indice_qualite_sol, 'statut' => $m->statut_global];
    }

    private function syntheseAir(array $vsOMS, ?array $vsIQAir, ?array $vsOW): array
    {
        $polluants = $vsOMS['polluants_hors_norme'] ?? [];
        return [
            'conforme_oms'    => $vsOMS['conformite_globale'] ?? false,
            'nb_depassements' => count($polluants),
            'alertes'         => $polluants,
            'statut'          => $vsOMS['statut_global'] ?? 'inconnu',
            'message'         => count($polluants) > 0
                ? '⚠ Dépassement OMS : ' . implode(', ', $polluants) . ' — mesures correctives requises'
                : '✓ Qualité de l\'air conforme aux normes OMS 2021',
        ];
    }

    private function syntheseEau(array $vsOMS): array
    {
        $critiques = $vsOMS['parametres_critiques'] ?? [];
        $nc        = $vsOMS['nb_non_conformes'] ?? 0;
        return [
            'conforme_oms'     => $vsOMS['conformite_globale'] ?? false,
            'nb_depassements'  => $nc,
            'parametres_crit'  => $critiques,
            'statut'           => $vsOMS['statut_global'] ?? 'inconnu',
            'message'          => count($critiques) > 0
                ? '⚠ URGENCE EAU — Paramètres critiques : ' . implode(', ', $critiques)
                : ($nc > 0 ? 'Non-conformités eau — traitement requis' : '✓ Eau conforme normes OMS'),
        ];
    }

    private function syntheseSol(array $vsFAO): array
    {
        $critiques = $vsFAO['contaminants_critiques'] ?? [];
        $nc        = $vsFAO['nb_non_conformes'] ?? 0;
        return [
            'conforme_fao'    => $vsFAO['conformite_globale'] ?? false,
            'nb_depassements' => $nc,
            'contaminants'    => $critiques,
            'statut'          => $vsFAO['statut_global'] ?? 'inconnu',
            'message'         => count($critiques) > 0
                ? '⚠ CONTAMINATION SOL — ' . implode(', ', $critiques) . ' — dépollution requise'
                : ($nc > 0 ? 'Sol dégradé — amendements recommandés' : '✓ Sol conforme normes FAO'),
        ];
    }
}
