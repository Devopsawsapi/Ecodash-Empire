<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PgesAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PgesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = PgesAction::with(['site', 'responsable'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->module,  fn ($q, $m)  => $q->where('module', $m))
            ->when($request->statut,  fn ($q, $s)  => $q->where('statut', $s))
            ->get();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'                => 'required|exists:sites,id',
            'titre'                  => 'required|string',
            'description'            => 'required|string',
            'module'                 => 'required|in:eau,air,sol,social,biodiversite,patrimoine,securite',
            'type_mesure'            => 'required|in:attenuation,compensation,prevention,surveillance,renforcement_capacites',
            'impact_cible'           => 'required|string',
            'phase_projet'           => 'required|in:preparation,construction,exploitation,fermeture,rehabilitation',
            'indicateur_performance' => 'required|string',
            'valeur_cible'           => 'required|string',
            'budget_prevu'           => 'nullable|numeric',
            'date_debut_prevue'      => 'nullable|date',
            'date_fin_prevue'        => 'nullable|date',
        ]);

        $validated['code_action']    = 'PGES-' . strtoupper($validated['module'][0]) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $validated['responsable_id'] = Auth::id();

        $action = PgesAction::create($validated);

        return response()->json(['data' => $action], 201);
    }

    public function show(PgesAction $action): JsonResponse
    {
        return response()->json(['data' => $action->load(['site', 'responsable'])]);
    }

    public function destroy(PgesAction $action): JsonResponse
    {
        $action->delete();
        return response()->json(['message' => 'Action PGES supprimée']);
    }

        public function update(PgesAction $action, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut'          => 'sometimes|string',
            'taux_realisation' => 'sometimes|numeric|between:0,100',
            'valeur_actuelle' => 'sometimes|nullable|string',
            'conformite'      => 'sometimes|string',
            'budget_realise'  => 'sometimes|nullable|numeric',
            'observations'    => 'sometimes|nullable|string',
        ]);

        if (isset($validated['statut']) && $validated['statut'] === 'realisee') {
            $validated['date_realisation'] = now();
        }

        $action->update($validated);

        return response()->json(['data' => $action]);
    }

    public function tableauBord(): JsonResponse
    {
        $total = PgesAction::count();

        return response()->json(['data' => [
            'total'          => $total,
            'par_module'     => PgesAction::selectRaw('module, count(*) as total, sum(taux_realisation)/count(*) as avancement')
                ->groupBy('module')
                ->get(),
            'par_conformite' => PgesAction::selectRaw('conformite, count(*) as total')
                ->groupBy('conformite')
                ->pluck('total', 'conformite'),
            'par_statut'     => PgesAction::selectRaw('statut, count(*) as total')
                ->groupBy('statut')
                ->pluck('total', 'statut'),
            'avancement_global'      => $total > 0 ? round(PgesAction::avg('taux_realisation'), 1) : 0,
            'budget_total_prevu'     => PgesAction::sum('budget_prevu'),
            'budget_total_realise'   => PgesAction::sum('budget_realise'),
        ]]);
    }
}
