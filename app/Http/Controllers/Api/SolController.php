<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesureSol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = MesureSol::with(['site', 'technicien'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->statut,  fn ($q, $s)  => $q->where('statut_global', $s))
            ->orderBy('date_prelevement', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'                  => 'required|exists:sites,id',
            'date_prelevement'         => 'required|date',
            'ph_sol'                   => 'nullable|numeric|between:0,14',
            'humidite_sol'             => 'nullable|numeric|between:0,100',
            'matiere_organique'        => 'nullable|numeric|min:0',
            'carbone_organique'        => 'nullable|numeric|min:0',
            'azote_total'              => 'nullable|numeric|min:0',
            'phosphore_dispo'          => 'nullable|numeric|min:0',
            'texture'                  => 'nullable|in:argileuse,limoneuse,sableuse,argilo_limoneuse,franco_sableuse,franche',
            'plomb_sol'                => 'nullable|numeric|min:0',
            'mercure_sol'              => 'nullable|numeric|min:0',
            'arsenic_sol'              => 'nullable|numeric|min:0',
            'cadmium_sol'              => 'nullable|numeric|min:0',
            'hydrocarbures_totaux'     => 'nullable|numeric|min:0',
            'pesticides_totaux'        => 'nullable|numeric|min:0',
            'presence_huile'           => 'nullable|boolean',
            'niveau_erosion'           => 'nullable|in:nul,faible,modere,fort,tres_fort',
            'taux_couverture_vegetale' => 'nullable|numeric|between:0,100',
            'usage_sol'                => 'nullable|in:agricole,forestier,industriel,residentiel,friche,minier',
            'profondeur_prelevement'   => 'nullable|numeric|min:0',
            'observations'             => 'nullable|string',
            'photos.*'                 => 'nullable|image|max:5120',
        ]);

        $validated['technicien_id'] = Auth::id();

        if ($request->hasFile('photos')) {
            $validated['photos'] = collect($request->file('photos'))
                ->map(fn ($p) => $p->store('sol/photos', 'public'))
                ->toArray();
        }

        $mesure = MesureSol::create($validated);

        return response()->json([
            'data'    => $mesure,
            'message' => 'Analyse sol enregistrée',
            'alertes' => $mesure->anomalies,
        ], 201);
    }

    public function show(MesureSol $mesure): JsonResponse
    {
        return response()->json(['data' => $mesure->load(['site', 'technicien'])]);
    }

    public function update(Request $request, MesureSol $mesure): JsonResponse
    {
        $validated = $request->validate([
            'ph_sol'                   => 'sometimes|nullable|numeric|between:0,14',
            'humidite_sol'             => 'sometimes|nullable|numeric|between:0,100',
            'matiere_organique'        => 'sometimes|nullable|numeric|min:0',
            'plomb_sol'                => 'sometimes|nullable|numeric|min:0',
            'mercure_sol'              => 'sometimes|nullable|numeric|min:0',
            'arsenic_sol'              => 'sometimes|nullable|numeric|min:0',
            'cadmium_sol'              => 'sometimes|nullable|numeric|min:0',
            'hydrocarbures_totaux'     => 'sometimes|nullable|numeric|min:0',
            'niveau_erosion'           => 'sometimes|nullable|in:nul,faible,modere,fort,tres_fort',
            'taux_couverture_vegetale' => 'sometimes|nullable|numeric|between:0,100',
            'observations'             => 'sometimes|nullable|string',
        ]);

        $mesure->update($validated);

        return response()->json(['data' => $mesure->fresh(), 'message' => 'Mesure mise à jour']);
    }

    public function destroy(MesureSol $mesure): JsonResponse
    {
        $mesure->delete();
        return response()->json(['message' => 'Mesure supprimée']);
    }

        public function valider(MesureSol $mesure): JsonResponse
    {
        $mesure->update([
            'valide'      => true,
            'validee_par' => Auth::id(),
        ]);

        return response()->json(['data' => $mesure, 'message' => 'Mesure validée']);
    }

    public function statistiquesSite(int $siteId): JsonResponse
    {
        $mesures = MesureSol::where('site_id', $siteId)
            ->where('date_prelevement', '>=', now()->subDays(90))
            ->get();

        return response()->json(['data' => [
            'total_analyses'       => $mesures->count(),
            'distribution_statuts' => [
                'sain'      => $mesures->where('statut_global', 'sain')->count(),
                'attention' => $mesures->where('statut_global', 'attention')->count(),
                'degrade'   => $mesures->where('statut_global', 'degrade')->count(),
                'critique'  => $mesures->where('statut_global', 'critique')->count(),
            ],
            'metaux_lourds_moyens' => [
                'pb' => round($mesures->avg('plomb_sol'), 2),
                'hg' => round($mesures->avg('mercure_sol'), 3),
                'as' => round($mesures->avg('arsenic_sol'), 2),
                'cd' => round($mesures->avg('cadmium_sol'), 3),
            ],
            'iqs_moyen'        => round($mesures->avg('indice_qualite_sol'), 1),
            'ph_moyen'         => round($mesures->avg('ph_sol'), 2),
            'matiere_organique_moy' => round($mesures->avg('matiere_organique'), 2),
        ]]);
    }
}
