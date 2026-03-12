<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndicateurEmploi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IndicateursEmploiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = IndicateurEmploi::with(['site', 'agent'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->annee,   fn ($q, $a)  => $q->where('annee', $a))
            ->orderBy('annee', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'              => 'required|exists:sites,id',
            'emplois_directs_crees' => 'required|integer|min:0',
            'emplois_locaux'       => 'nullable|integer|min:0',
            'pct_emplois_femmes'   => 'nullable|numeric|between:0,100',
            'pct_emplois_jeunes'   => 'nullable|numeric|between:0,100',
            'salaire_moyen_local'  => 'nullable|numeric|min:0',
            'fournisseurs_locaux'  => 'nullable|integer|min:0',
            'investissement_social' => 'nullable|numeric|min:0',
            'projets_sociaux'      => 'nullable|array',
            'annee'                => 'required|integer|min:2000|max:2100',
            'observations'         => 'nullable|string',
        ]);

        $validated['agent_id'] = Auth::id();
        $indicateur = IndicateurEmploi::create($validated);

        return response()->json(['data' => $indicateur, 'message' => 'Indicateur emploi enregistré'], 201);
    }

    public function show(IndicateurEmploi $emploi): JsonResponse
    {
        return response()->json(['data' => $emploi->load(['site', 'agent'])]);
    }

    public function update(Request $request, IndicateurEmploi $emploi): JsonResponse
    {
        $validated = $request->validate([
            'emplois_directs_crees'  => 'sometimes|integer|min:0',
            'emplois_locaux'         => 'sometimes|integer|min:0',
            'pct_emplois_femmes'     => 'sometimes|numeric|between:0,100',
            'pct_emplois_jeunes'     => 'sometimes|numeric|between:0,100',
            'investissement_social'  => 'sometimes|numeric|min:0',
            'projets_sociaux'        => 'sometimes|array',
            'observations'           => 'sometimes|nullable|string',
        ]);

        $emploi->update($validated);

        return response()->json(['data' => $emploi, 'message' => 'Indicateur mis à jour']);
    }

    public function destroy(IndicateurEmploi $emploi): JsonResponse
    {
        $emploi->delete();

        return response()->json(['message' => 'Indicateur supprimé']);
    }
}
