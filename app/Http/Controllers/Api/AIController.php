<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIAnalysisService;
use App\Services\International\AirQualityService;
use App\Services\International\WaterQualityService;
use App\Services\International\SoilQualityService;
use App\Models\{MesureAir, MesureEau, MesureSol, Site, Plainte, PgesAction};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AIController — Intelligence Artificielle EcoDash
 * Endpoints IA : analyse, prédiction, alertes, rapport, chatbot
 */
class AIController extends Controller
{
    public function __construct(
        private AIAnalysisService  $ai,
        private AirQualityService  $airSvc,
        private WaterQualityService $waterSvc,
        private SoilQualityService  $soilSvc,
    ) {}

    // ── POST /api/ia/analyser ──────────────────────────────────────
    /**
     * Analyse IA d'une mesure terrain vs normes internationales
     * Body: { type: 'eau|air|sol', mesure_id: int } ou { type: ..., donnees: {} }
     */
    public function analyser(Request $request): JsonResponse
    {
        $request->validate([
            'type'      => 'required|in:eau,air,sol,biodiversite,social',
            'mesure_id' => 'nullable|integer',
            'donnees'   => 'nullable|array',
            'site_nom'  => 'nullable|string',
        ]);

        $type     = $request->type;
        $siteNom  = $request->site_nom ?? '';
        $donnees  = [];
        $refExterne = null;

        // Charger depuis la base si mesure_id fourni
        if ($request->mesure_id) {
            [$donnees, $siteNom, $refExterne] = match ($type) {
                'eau' => $this->chargerEau($request->mesure_id),
                'air' => $this->chargerAir($request->mesure_id),
                'sol' => $this->chargerSol($request->mesure_id),
                default => [$request->donnees ?? [], $siteNom, null],
            };
        } else {
            $donnees = $request->donnees ?? [];
        }

        if (empty($donnees)) {
            return response()->json(['error' => 'Aucune donnée à analyser'], 422);
        }

        $analyse = $this->ai->analyserMesure($type, $donnees, $siteNom, $refExterne);

        return response()->json([
            'type'         => $type,
            'site'         => $siteNom,
            'analyse_ia'   => $analyse,
            'genere_le'    => now()->toISOString(),
        ]);
    }

    // ── POST /api/ia/predire ───────────────────────────────────────
    /**
     * Prédiction de tendances sur historique
     * Body: { type: 'eau|air|sol', site_id: int, horizon_jours: 30 }
     */
    public function predire(Request $request): JsonResponse
    {
        $request->validate([
            'type'         => 'required|in:eau,air,sol',
            'site_id'      => 'required|integer|exists:sites,id',
            'horizon_jours'=> 'nullable|integer|between:7,90',
        ]);

        $siteId  = $request->site_id;
        $horizon = $request->horizon_jours ?? 30;
        $historique = [];

        switch ($request->type) {
            case 'eau':
                $historique = MesureEau::where('site_id', $siteId)
                    ->orderBy('date_prelevement')
                    ->take(60)
                    ->get(['date_prelevement as date', 'ph', 'turbidite', 'oxygene_dissous', 'plomb', 'nitrates', 'indice_qualite'])
                    ->toArray();
                break;
            case 'air':
                $historique = MesureAir::where('site_id', $siteId)
                    ->orderBy('date_mesure')
                    ->take(60)
                    ->get(['date_mesure as date', 'pm25', 'pm10', 'no2', 'so2', 'o3', 'iqa'])
                    ->toArray();
                break;
            case 'sol':
                $historique = MesureSol::where('site_id', $siteId)
                    ->orderBy('date_prelevement')
                    ->take(30)
                    ->get(['date_prelevement as date', 'ph_sol', 'matiere_organique', 'plomb_sol', 'arsenic_sol', 'indice_qualite_sol'])
                    ->toArray();
                break;
        }

        if (count($historique) < 3) {
            return response()->json(['error' => 'Historique insuffisant (minimum 3 mesures requises)'], 422);
        }

        $predictions = $this->ai->predireTendances($request->type, $historique, $horizon);

        return response()->json([
            'type'           => $request->type,
            'site_id'        => $siteId,
            'horizon_jours'  => $horizon,
            'nb_mesures_base'=> count($historique),
            'predictions'    => $predictions,
            'genere_le'      => now()->toISOString(),
        ]);
    }

    // ── GET|POST /api/ia/alertes ───────────────────────────────────
    /**
     * Génère des alertes intelligentes en analysant toutes les données récentes
     */
    public function alertes(Request $request): JsonResponse
    {
        // Agréger toutes les données récentes
        $donnees = [
            'eau' => [
                'derniers_7j'       => MesureEau::where('date_prelevement', '>=', now()->subDays(7))
                    ->with('site:id,nom')
                    ->orderBy('date_prelevement', 'desc')
                    ->take(20)
                    ->get(['site_id', 'date_prelevement', 'ph', 'plomb', 'mercure', 'arsenic', 'e_coli', 'indice_qualite', 'statut_global', 'anomalies'])
                    ->toArray(),
                'sites_critiques'   => MesureEau::where('statut_global', 'critique')
                    ->where('date_prelevement', '>=', now()->subDays(7))
                    ->distinct('site_id')->count('site_id'),
            ],
            'air' => [
                'derniers_7j'       => MesureAir::where('date_mesure', '>=', now()->subDays(7))
                    ->with('site:id,nom')
                    ->orderBy('date_mesure', 'desc')
                    ->take(20)
                    ->get(['site_id', 'date_mesure', 'pm25', 'pm10', 'no2', 'so2', 'iqa', 'statut_global', 'polluants_dominants'])
                    ->toArray(),
                'sites_mauvais_air' => MesureAir::whereIn('statut_global', ['mauvais', 'critique'])
                    ->where('date_mesure', '>=', now()->subDays(3))
                    ->distinct('site_id')->count('site_id'),
            ],
            'sol' => [
                'derniers_30j'      => MesureSol::where('date_prelevement', '>=', now()->subDays(30))
                    ->with('site:id,nom')
                    ->orderBy('date_prelevement', 'desc')
                    ->take(10)
                    ->get(['site_id', 'date_prelevement', 'ph_sol', 'plomb_sol', 'arsenic_sol', 'hydrocarbures_totaux', 'indice_qualite_sol', 'statut_global'])
                    ->toArray(),
            ],
            'social' => [
                'plaintes_en_cours' => Plainte::parStatut('en_cours')->count(),
                'plaintes_retard'   => Plainte::parStatut('en_retard')->count(),
                'pges_nc'           => PgesAction::where('conformite', 'non_conforme')->count(),
            ],
        ];

        $alertes = $this->ai->genererAlertes($donnees);

        return response()->json([
            'alertes'    => $alertes,
            'genere_le'  => now()->toISOString(),
            'periode'    => '7 derniers jours',
        ]);
    }

    // ── POST /api/ia/rapport ───────────────────────────────────────
    /**
     * Génère un rapport narratif IA
     * Body: { periode: 'Mars 2026', site_id?: int, type_rapport: 'mensuel' }
     */
    public function rapport(Request $request): JsonResponse
    {
        $request->validate([
            'periode'      => 'required|string',
            'site_id'      => 'nullable|integer|exists:sites,id',
            'type_rapport' => 'nullable|in:mensuel,trimestriel,annuel,incident',
        ]);

        $siteId = $request->site_id;
        $siteNom = $siteId ? Site::find($siteId)?->nom ?? 'Site ' . $siteId : 'Tous les sites';

        // Agrégat données pour le rapport
        $debut  = now()->startOfMonth();
        $eauQ   = MesureEau::where('date_prelevement', '>=', $debut)->when($siteId, fn($q) => $q->where('site_id', $siteId));
        $airQ   = MesureAir::where('date_mesure', '>=', $debut)->when($siteId, fn($q) => $q->where('site_id', $siteId));
        $solQ   = MesureSol::where('date_prelevement', '>=', $debut)->when($siteId, fn($q) => $q->where('site_id', $siteId));

        $donnees = [
            'eau' => [
                'nb_mesures'  => $eauQ->count(),
                'ph_moyen'    => round($eauQ->avg('ph') ?? 0, 2),
                'iqa_moyen'   => round($eauQ->avg('indice_qualite') ?? 0, 1),
                'critiques'   => $eauQ->where('statut_global', 'critique')->count(),
                'conformes'   => $eauQ->where('statut_global', 'conforme')->count(),
            ],
            'air' => [
                'nb_mesures'  => $airQ->count(),
                'pm25_moyen'  => round($airQ->avg('pm25') ?? 0, 1),
                'iqa_moyen'   => round($airQ->avg('iqa') ?? 0, 0),
                'sites_mauvais'=> $airQ->whereIn('statut_global', ['mauvais', 'critique'])->distinct('site_id')->count('site_id'),
            ],
            'sol' => [
                'nb_mesures'  => $solQ->count(),
                'ph_moyen'    => round($solQ->avg('ph_sol') ?? 0, 2),
                'iqs_moyen'   => round($solQ->avg('indice_qualite_sol') ?? 0, 1),
                'degrades'    => $solQ->whereIn('statut_global', ['degrade', 'critique'])->count(),
            ],
            'social' => [
                'plaintes_total'   => Plainte::count(),
                'plaintes_resolues'=> Plainte::parStatut('resolue')->count(),
                'pges_conformite_pct' => PgesAction::count() > 0
                    ? round(PgesAction::where('conformite', 'conforme')->count() / PgesAction::count() * 100, 1)
                    : 0,
            ],
        ];

        $rapport = $this->ai->genererRapportNarratif(
            $request->periode,
            $donnees,
            $siteNom,
            $request->type_rapport ?? 'mensuel'
        );

        return response()->json([
            'rapport'    => $rapport,
            'periode'    => $request->periode,
            'site'       => $siteNom,
            'genere_le'  => now()->toISOString(),
        ]);
    }

    // ── POST /api/ia/chat ──────────────────────────────────────────
    /**
     * Chatbot IA environnemental
     * Body: { question: string, contexte?: {}, historique?: [] }
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'question'   => 'required|string|max:1000',
            'contexte'   => 'nullable|array',
            'historique' => 'nullable|array',
        ]);

        // Si pas de contexte fourni, utiliser les KPIs du dashboard
        $contexte = $request->contexte;
        if (!$contexte) {
            $contexte = [
                'eau_iqa_moyen'    => MesureEau::where('date_prelevement', '>=', now()->subDays(7))->avg('indice_qualite'),
                'air_iqa_moyen'    => MesureAir::where('date_mesure', '>=', now()->subDays(7))->avg('iqa'),
                'sol_iqs_moyen'    => MesureSol::where('date_prelevement', '>=', now()->subDays(30))->avg('indice_qualite_sol'),
                'sites_critiques'  => MesureEau::where('statut_global', 'critique')->where('date_prelevement', '>=', now()->subDays(7))->distinct('site_id')->count('site_id'),
                'plaintes_actives' => Plainte::parStatut('en_cours')->count(),
            ];
        }

        $reponse = $this->ai->chat(
            $request->question,
            $contexte,
            $request->historique ?? []
        );

        return response()->json([
            'question' => $request->question,
            'reponse'  => $reponse,
            'timestamp'=> now()->toISOString(),
        ]);
    }

    // ── GET /api/ia/site/{id} ──────────────────────────────────────
    /**
     * Analyse IA complète d'un site (eau + air + sol + alertes)
     */
    public function analyseSite(int $id): JsonResponse
    {
        $site   = Site::findOrFail($id);
        $eau    = MesureEau::where('site_id', $id)->latest('date_prelevement')->first();
        $air    = MesureAir::where('site_id', $id)->latest('date_mesure')->first();
        $sol    = MesureSol::where('site_id', $id)->latest('date_prelevement')->first();

        $analyses = [];

        if ($eau) {
            [$donneesEau] = $this->chargerEau($eau->id);
            $analyses['eau'] = $this->ai->analyserMesure('eau', $donneesEau, $site->nom);
        }
        if ($air) {
            [$donneesAir] = $this->chargerAir($air->id);
            $analyses['air'] = $this->ai->analyserMesure('air', $donneesAir, $site->nom);
        }
        if ($sol) {
            [$donneesSol] = $this->chargerSol($sol->id);
            $analyses['sol'] = $this->ai->analyserMesure('sol', $donneesSol, $site->nom);
        }

        // Score global
        $scores = collect($analyses)->map(fn($a) => $a['score_qualite'] ?? 0);
        $scoreGlobal = $scores->count() > 0 ? round($scores->avg()) : null;

        return response()->json([
            'site'         => ['id' => $site->id, 'nom' => $site->nom],
            'analyses'     => $analyses,
            'score_global' => $scoreGlobal,
            'niveau_global'=> $scoreGlobal === null ? 'inconnu'
                : ($scoreGlobal >= 80 ? 'CONFORME' : ($scoreGlobal >= 50 ? 'ATTENTION' : 'CRITIQUE')),
            'genere_le'    => now()->toISOString(),
        ]);
    }

    // ── Helpers privés ─────────────────────────────────────────────

    private function chargerEau(int $id): array
    {
        $m = MesureEau::with('site')->findOrFail($id);
        $donnees = $m->only(['ph', 'turbidite', 'oxygene_dissous', 'conductivite', 'nitrates', 'dbo5', 'plomb', 'mercure', 'arsenic', 'cadmium', 'coliformes_totaux', 'e_coli', 'indice_qualite', 'statut_global', 'anomalies']);
        $ref = $this->waterSvc->comparerAvecNormes(array_merge($m->toArray(), ['site' => $m->site?->nom]));
        return [$donnees, $m->site?->nom ?? '', $ref];
    }

    private function chargerAir(int $id): array
    {
        $m = MesureAir::with('site')->findOrFail($id);
        $donnees = $m->only(['pm25', 'pm10', 'co', 'no2', 'so2', 'o3', 'nh3', 'niveau_bruit_db', 'iqa', 'categorie_iqa', 'statut_global', 'polluants_dominants']);
        $ref = $this->airSvc->comparerAvecNormes(array_merge($m->toArray(), ['site' => $m->site?->nom]));
        return [$donnees, $m->site?->nom ?? '', $ref];
    }

    private function chargerSol(int $id): array
    {
        $m = MesureSol::with('site')->findOrFail($id);
        $donnees = $m->only(['ph_sol', 'matiere_organique', 'plomb_sol', 'mercure_sol', 'arsenic_sol', 'cadmium_sol', 'chrome_sol', 'hydrocarbures_totaux', 'pesticides_totaux', 'niveau_erosion', 'taux_couverture_vegetale', 'indice_qualite_sol', 'statut_global']);
        $ref = $this->soilSvc->comparerAvecNormes(array_merge($m->toArray(), ['site' => $m->site?->nom]));
        return [$donnees, $m->site?->nom ?? '', $ref];
    }
}
