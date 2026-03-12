<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{MesureAir, MesureEau, MesureSol, PgesAction, Plainte, Site};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RapportController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = Site::where('actif', true)->select('id', 'nom', 'code_site')->get();
        $rapports = [
            ['id' => 'dashboard-global', 'titre' => 'Rapport global EcoDash', 'type' => 'global'],
            ['id' => 'plaintes-export',  'titre' => 'Export des plaintes',     'type' => 'social'],
        ];
        foreach ($sites as $site) {
            $rapports[] = ['id' => 'site-'.$site->id, 'titre' => 'Rapport — '.$site->nom, 'type' => 'site', 'site_id' => $site->id];
        }
        return response()->json(['data' => $rapports, 'total' => count($rapports)]);
    }

    public function generer(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:global,site,plaintes,pges', 'site_id' => 'required_if:type,site,pges|exists:sites,id']);
        return match ($request->type) {
            'global'   => $this->rapportGlobal(),
            'site'     => $this->siteComplet((int)$request->site_id),
            'plaintes' => $this->plaintesExport(),
            'pges'     => $this->pges((int)$request->site_id),
        };
    }

    public function show(string $id): JsonResponse
    {
        if ($id === 'dashboard-global') return $this->rapportGlobal();
        if ($id === 'plaintes-export')  return $this->plaintesExport();
        if (str_starts_with($id, 'site-')) return $this->siteComplet((int)str_replace('site-', '', $id));
        return response()->json(['message' => 'Rapport introuvable'], 404);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['message' => 'Rapport supprimé']);
    }

    // ── Rapport complet d'un site ─────────────────────────────
    public function siteComplet(int $siteId): JsonResponse
    {
        $site = Site::with([
            'zonesInfluence',
            'mesuresEau'   => fn ($q) => $q->latest('date_prelevement')->limit(30),
            'mesuresAir'   => fn ($q) => $q->latest('date_mesure')->limit(30),
            'mesuresSol'   => fn ($q) => $q->latest('date_prelevement')->limit(30),
            'pgesActions',
            'biodiversite' => fn ($q) => $q->latest('date_observation')->limit(20),
        ])->findOrFail($siteId);

        return response()->json(['data' => [
            'site'                => $site,
            'statut_global'       => $site->statut_global ?? 'inconnu',
            'mesures_eau'         => $site->mesuresEau,
            'mesures_air'         => $site->mesuresAir,
            'mesures_sol'         => $site->mesuresSol,
            'pges'                => $site->pgesActions,
            'biodiversite'        => $site->biodiversite,
            'plaintes_par_statut' => Plainte::where('site_id', $siteId)->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut'),
            'genere_le'           => now()->toISOString(),
        ]]);
    }

    // ── Rapport PGES d'un site ────────────────────────────────
    public function pges(int $siteId): JsonResponse
    {
        $site    = Site::findOrFail($siteId);
        $actions = PgesAction::where('site_id', $siteId)->with('responsable')->get();

        return response()->json(['data' => [
            'site'              => $site,
            'actions'           => $actions,
            'total'             => $actions->count(),
            'avancement_global' => round($actions->avg('taux_realisation') ?? 0, 1),
            'budget_prevu'      => $actions->sum('budget_prevu'),
            'budget_realise'    => $actions->sum('budget_realise'),
            'par_module'        => $actions->groupBy('module')->map->count(),
            'genere_le'         => now()->toISOString(),
        ]]);
    }

    // ── Export plaintes ───────────────────────────────────────
    public function plaintesExport(): JsonResponse
    {
        $plaintes = Plainte::with(['site', 'agent'])->orderBy('created_at', 'desc')->get()
            ->map(fn ($p) => [
                'reference'         => $p->reference,
                'site'              => $p->site?->nom,
                'sujet'             => $p->sujet,
                'categorie'         => $p->categorie,
                'statut'            => $p->statut,
                'priorite'          => $p->priorite,
                'declarant'         => $p->declarant_anonyme ? 'Anonyme' : $p->declarant_nom,
                'date_soumission'   => $p->created_at->format('d/m/Y'),
                'date_resolution'   => $p->date_resolution?->format('d/m/Y'),
                'note_satisfaction' => $p->note_satisfaction,
                'agent_assigne'     => $p->agent?->name,
            ]);

        return response()->json(['data' => $plaintes, 'total' => $plaintes->count(), 'genere_le' => now()->toISOString()]);
    }

    private function rapportGlobal(): JsonResponse
    {
        $sites = Site::where('actif', true)->get();
        return response()->json(['data' => [
            'titre'   => 'Rapport Global EcoDash',
            'sites'   => $sites,
            'totaux'  => [
                'sites'        => $sites->count(),
                'mesures_eau'  => MesureEau::count(),
                'mesures_air'  => MesureAir::count(),
                'mesures_sol'  => MesureSol::count(),
                'plaintes'     => Plainte::count(),
                'pges_actions' => PgesAction::count(),
            ],
            'genere_le'  => now()->toISOString(),
            'genere_par' => Auth::user()?->name,
        ]]);
    }
}
