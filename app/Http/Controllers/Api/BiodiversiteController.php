<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ObservationBiodiversite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiodiversiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = ObservationBiodiversite::with(['site', 'observateur'])
            ->when($request->site_id,     fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->compartiment, fn ($q, $c) => $q->where('compartiment', $c))
            ->when($request->statut_iucn,  fn ($q, $s) => $q->where('statut_iucn', $s))
            ->orderBy('date_observation', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'             => 'required|exists:sites,id',
            'compartiment'        => 'required|in:flore,faune_terrestre,faune_aquatique,avifaune,ichtyofaune,entomofaune',
            'nom_espece'          => 'required|string',
            'nom_scientifique'    => 'nullable|string',
            'nombre_individus'    => 'nullable|integer|min:0',
            'statut_iucn'         => 'nullable|in:LC,NT,VU,EN,CR,EW,EX,DD',
            'espece_endemique'    => 'nullable|boolean',
            'espece_invasive'     => 'nullable|boolean',
            'tendance_population' => 'nullable|in:stable,en_augmentation,en_diminution,inconnue',
            'latitude_obs'        => 'nullable|numeric',
            'longitude_obs'       => 'nullable|numeric',
            'date_observation'    => 'required|date',
            'notes'               => 'nullable|string',
            'photos.*'            => 'nullable|image|max:5120',
        ]);

        $validated['observateur_id'] = Auth::id();

        if ($request->hasFile('photos')) {
            $validated['photos'] = collect($request->file('photos'))
                ->map(fn ($p) => $p->store('biodiversite/photos', 'public'))
                ->toArray();
        }

        $obs = ObservationBiodiversite::create($validated);

        return response()->json(['data' => $obs, 'message' => 'Observation enregistrée'], 201);
    }

    public function show(ObservationBiodiversite $observation): JsonResponse
    {
        return response()->json(['data' => $observation->load(['site', 'observateur'])]);
    }

    public function update(Request $request, ObservationBiodiversite $observation): JsonResponse
    {
        $validated = $request->validate([
            'nombre_individus'    => 'sometimes|nullable|integer|min:0',
            'statut_iucn'         => 'sometimes|nullable|in:LC,NT,VU,EN,CR,EW,EX,DD',
            'tendance_population' => 'sometimes|nullable|in:stable,en_augmentation,en_diminution,inconnue',
            'notes'               => 'sometimes|nullable|string',
        ]);

        $observation->update($validated);

        return response()->json(['data' => $observation->fresh(), 'message' => 'Observation mise à jour']);
    }

    public function destroy(ObservationBiodiversite $observation): JsonResponse
    {
        $observation->delete();
        return response()->json(['message' => 'Observation supprimée']);
    }

        public function statistiquesSite(int $siteId): JsonResponse
    {
        $obs = ObservationBiodiversite::where('site_id', $siteId)->get();

        return response()->json(['data' => [
            'total_observations' => $obs->count(),
            'especes_uniques'    => $obs->unique('nom_espece')->count(),
            'especes_menacees'   => $obs->whereIn('statut_iucn', ['VU', 'EN', 'CR'])->count(),
            'especes_endemiques' => $obs->where('espece_endemique', true)->count(),
            'especes_invasives'  => $obs->where('espece_invasive', true)->count(),
            'par_compartiment'   => $obs->groupBy('compartiment')->map->count(),
            'par_statut_iucn'    => $obs->groupBy('statut_iucn')->map->count(),
        ]]);
    }
}
