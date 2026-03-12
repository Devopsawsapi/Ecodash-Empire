<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesureEau;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EauController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = MesureEau::with(['site', 'technicien'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->statut,  fn ($q, $s)  => $q->where('statut_global', $s))
            ->orderBy('date_prelevement', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'           => 'required|exists:sites,id',
            'date_prelevement'  => 'required|date',
            'ph'                => 'nullable|numeric|between:0,14',
            'turbidite'         => 'nullable|numeric|min:0',
            'oxygene_dissous'   => 'nullable|numeric|min:0',
            'conductivite'      => 'nullable|numeric|min:0',
            'temperature_eau'   => 'nullable|numeric|between:-10,60',
            'tds'               => 'nullable|numeric|min:0',
            'nitrates'          => 'nullable|numeric|min:0',
            'dbo5'              => 'nullable|numeric|min:0',
            'plomb'             => 'nullable|numeric|min:0',
            'mercure'           => 'nullable|numeric|min:0',
            'arsenic'           => 'nullable|numeric|min:0',
            'cadmium'           => 'nullable|numeric|min:0',
            'chrome'            => 'nullable|numeric|min:0',
            'coliformes_totaux' => 'nullable|integer|min:0',
            'e_coli'            => 'nullable|integer|min:0',
            'type_source'       => 'nullable|in:surface,souterraine,traitement,distribution',
            'usage'             => 'nullable|in:potable,irrigation,industriel,peche',
            'observations'      => 'nullable|string',
            'photo_prelevement' => 'nullable|image|max:5120',
        ]);

        $validated['technicien_id'] = Auth::id();

        if ($request->hasFile('photo_prelevement')) {
            $validated['photo_prelevement'] = $request->file('photo_prelevement')
                ->store('eau/photos', 'public');
        }

        $mesure = MesureEau::create($validated);

        return response()->json([
            'data'     => $mesure,
            'message'  => 'Mesure eau enregistrée',
            'alertes'  => $mesure->anomalies,
        ], 201);
    }

    public function show(MesureEau $mesure): JsonResponse
    {
        return response()->json(['data' => $mesure->load(['site', 'technicien'])]);
    }

    public function update(Request $request, MesureEau $mesure): JsonResponse
    {
        $validated = $request->validate([
            'ph'                => 'sometimes|nullable|numeric|between:0,14',
            'turbidite'         => 'sometimes|nullable|numeric|min:0',
            'oxygene_dissous'   => 'sometimes|nullable|numeric|min:0',
            'conductivite'      => 'sometimes|nullable|numeric|min:0',
            'temperature_eau'   => 'sometimes|nullable|numeric|between:-10,60',
            'tds'               => 'sometimes|nullable|numeric|min:0',
            'nitrates'          => 'sometimes|nullable|numeric|min:0',
            'dbo5'              => 'sometimes|nullable|numeric|min:0',
            'plomb'             => 'sometimes|nullable|numeric|min:0',
            'mercure'           => 'sometimes|nullable|numeric|min:0',
            'arsenic'           => 'sometimes|nullable|numeric|min:0',
            'coliformes_totaux' => 'sometimes|nullable|integer|min:0',
            'e_coli'            => 'sometimes|nullable|integer|min:0',
            'observations'      => 'sometimes|nullable|string',
        ]);

        $mesure->update($validated);

        return response()->json(['data' => $mesure->fresh(), 'message' => 'Mesure mise à jour']);
    }

    public function destroy(MesureEau $mesure): JsonResponse
    {
        $mesure->delete();
        return response()->json(['message' => 'Mesure supprimée']);
    }

        public function valider(MesureEau $mesure): JsonResponse
    {
        $mesure->update([
            'valide'      => true,
            'validee_le'  => now(),
            'validee_par' => Auth::id(),
        ]);

        return response()->json(['data' => $mesure, 'message' => 'Mesure validée']);
    }

    public function statistiquesSite(Request $request, int $siteId): JsonResponse
    {
        $jours   = $request->jours ?? 30;
        $mesures = MesureEau::where('site_id', $siteId)
            ->where('date_prelevement', '>=', now()->subDays($jours))
            ->get();

        return response()->json(['data' => [
            'evolution_ph'       => $mesures->map(fn ($m) => ['date' => $m->date_prelevement->format('d/m'), 'v' => $m->ph])->filter(fn ($m) => $m['v'])->values(),
            'evolution_o2'       => $mesures->map(fn ($m) => ['date' => $m->date_prelevement->format('d/m'), 'v' => $m->oxygene_dissous])->filter(fn ($m) => $m['v'])->values(),
            'evolution_turbidite' => $mesures->map(fn ($m) => ['date' => $m->date_prelevement->format('d/m'), 'v' => $m->turbidite])->filter(fn ($m) => $m['v'])->values(),
            'metaux_lourds'      => [
                'pb' => round($mesures->avg('plomb'), 4),
                'hg' => round($mesures->avg('mercure'), 5),
                'as' => round($mesures->avg('arsenic'), 4),
            ],
            'distribution_statuts' => [
                'conforme'  => $mesures->where('statut_global', 'conforme')->count(),
                'attention' => $mesures->where('statut_global', 'attention')->count(),
                'critique'  => $mesures->where('statut_global', 'critique')->count(),
            ],
            'iqa_moyen' => round($mesures->avg('indice_qualite'), 1),
        ]]);
    }
}
