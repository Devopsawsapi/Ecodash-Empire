<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sites = Site::when($request->actif !== null, fn ($q) => $q->where('actif', $request->boolean('actif')))
            ->when($request->type, fn ($q, $t) => $q->where('type_site', $t))
            ->with(['derniereEau', 'dernierAir', 'dernierSol'])
            ->paginate($request->per_page ?? 20);

        return response()->json($sites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'            => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:sites',
            'description'    => 'nullable|string',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'localite'       => 'nullable|string|max:255',
            'province'       => 'nullable|string|max:255',
            'pays'           => 'nullable|string|max:100',
            'type_site'      => 'required|in:mine,carriere,usine,barrage,pipeline,forage,autre',
            'modules_actifs' => 'nullable|array',
            'photo'          => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('sites/photos', 'public');
        }

        $site = Site::create($validated);

        return response()->json(['data' => $site, 'message' => 'Site créé'], 201);
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json(['data' => $site->load([
            'derniereEau', 'dernierAir', 'dernierSol',
            'zonesInfluence', 'pgesActions',
        ])]);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'nom'            => 'sometimes|string|max:255',
            'description'    => 'sometimes|nullable|string',
            'latitude'       => 'sometimes|numeric|between:-90,90',
            'longitude'      => 'sometimes|numeric|between:-180,180',
            'localite'       => 'sometimes|nullable|string|max:255',
            'province'       => 'sometimes|nullable|string|max:255',
            'actif'          => 'sometimes|boolean',
            'modules_actifs' => 'sometimes|array',
        ]);

        $site->update($validated);

        return response()->json(['data' => $site, 'message' => 'Site mis à jour']);
    }

    public function destroy(Site $site): JsonResponse
    {
        $site->delete();

        return response()->json(['message' => 'Site archivé']);
    }

    /**
     * Résumé complet d'un site : dernières mesures + statistiques 30 j.
     */
    public function resume(Site $site): JsonResponse
    {
        $site->load(['derniereEau', 'dernierAir', 'dernierSol', 'zonesInfluence']);

        return response()->json(['data' => [
            'site'          => $site,
            'statut_global' => $site->statut_global,
            'derniere_eau'  => $site->derniereEau,
            'dernier_air'   => $site->dernierAir,
            'dernier_sol'   => $site->dernierSol,
            'nb_plaintes_ouvertes' => $site->plaintes()->whereNotIn('statut', ['resolue', 'rejetee', 'annulee'])->count(),
            'pges_avancement'      => round($site->pgesActions()->avg('taux_realisation') ?? 0, 1),
        ]]);
    }
}
