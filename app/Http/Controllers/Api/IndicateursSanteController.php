<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndicateurSante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IndicateursSanteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = IndicateurSante::with(['site', 'zone', 'agent'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->annee,   fn ($q, $a)  => $q->where('annee', $a))
            ->orderBy('annee', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'                     => 'required|exists:sites,id',
            'zone_id'                     => 'nullable|exists:zone_influences,id',
            'population_enquetee'         => 'required|integer|min:1',
            'nb_maladies_respiratoires'   => 'nullable|integer|min:0',
            'nb_maladies_diarrheeiques'   => 'nullable|integer|min:0',
            'nb_maladies_peau'            => 'nullable|integer|min:0',
            'taux_acces_eau_potable'      => 'nullable|numeric|between:0,100',
            'taux_acces_assainissement'   => 'nullable|numeric|between:0,100',
            'periode'                     => 'required|in:annuel,semestriel,trimestriel,mensuel',
            'annee'                       => 'required|integer|min:2000|max:2100',
            'trimestre'                   => 'nullable|integer|between:1,4',
            'observations'                => 'nullable|string',
        ]);

        $validated['agent_id'] = Auth::id();
        $indicateur = IndicateurSante::create($validated);

        return response()->json(['data' => $indicateur, 'message' => 'Indicateur santé enregistré'], 201);
    }

    public function show(IndicateurSante $sante): JsonResponse
    {
        return response()->json(['data' => $sante->load(['site', 'zone', 'agent'])]);
    }

    public function update(Request $request, IndicateurSante $sante): JsonResponse
    {
        $validated = $request->validate([
            'nb_maladies_respiratoires' => 'sometimes|integer|min:0',
            'nb_maladies_diarrheeiques' => 'sometimes|integer|min:0',
            'nb_maladies_peau'          => 'sometimes|integer|min:0',
            'taux_acces_eau_potable'    => 'sometimes|numeric|between:0,100',
            'taux_acces_assainissement' => 'sometimes|numeric|between:0,100',
            'observations'              => 'sometimes|nullable|string',
        ]);

        $sante->update($validated);

        return response()->json(['data' => $sante, 'message' => 'Indicateur mis à jour']);
    }

    public function destroy(IndicateurSante $sante): JsonResponse
    {
        $sante->delete();

        return response()->json(['message' => 'Indicateur supprimé']);
    }
}
