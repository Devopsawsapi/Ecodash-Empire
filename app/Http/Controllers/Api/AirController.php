<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MesureAir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AirController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = MesureAir::with(['site', 'technicien'])
            ->when($request->site_id, fn ($q, $id) => $q->where('site_id', $id))
            ->when($request->statut,  fn ($q, $s)  => $q->where('statut_global', $s))
            ->orderBy('date_mesure', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id'          => 'required|exists:sites,id',
            'date_mesure'      => 'required|date',
            'pm25'             => 'nullable|numeric|min:0',
            'pm10'             => 'nullable|numeric|min:0',
            'co2'              => 'nullable|numeric|min:0',
            'co'               => 'nullable|numeric|min:0',
            'no2'              => 'nullable|numeric|min:0',
            'so2'              => 'nullable|numeric|min:0',
            'o3'               => 'nullable|numeric|min:0',
            'h2s'              => 'nullable|numeric|min:0',
            'niveau_bruit_db'  => 'nullable|numeric|between:0,200',
            'temperature_air'  => 'nullable|numeric|between:-30,60',
            'humidite_relative' => 'nullable|numeric|between:0,100',
            'vitesse_vent'     => 'nullable|numeric|min:0',
            'direction_vent'   => 'nullable|string|max:3',
            'methode_mesure'   => 'nullable|in:capteur_fixe,capteur_portable,laboratoire,station_meteo',
            'observations'     => 'nullable|string',
        ]);

        $validated['technicien_id'] = Auth::id();
        $mesure = MesureAir::create($validated);

        return response()->json([
            'data'      => $mesure,
            'message'   => 'Mesure air enregistrée',
            'iqa'       => $mesure->iqa,
            'categorie' => $mesure->categorie_iqa,
        ], 201);
    }

    public function show(MesureAir $mesure): JsonResponse
    {
        return response()->json(['data' => $mesure->load(['site', 'technicien'])]);
    }

    public function update(Request $request, MesureAir $mesure): JsonResponse
    {
        $validated = $request->validate([
            'pm25'              => 'sometimes|nullable|numeric|min:0',
            'pm10'              => 'sometimes|nullable|numeric|min:0',
            'co2'               => 'sometimes|nullable|numeric|min:0',
            'co'                => 'sometimes|nullable|numeric|min:0',
            'no2'               => 'sometimes|nullable|numeric|min:0',
            'so2'               => 'sometimes|nullable|numeric|min:0',
            'o3'                => 'sometimes|nullable|numeric|min:0',
            'niveau_bruit_db'   => 'sometimes|nullable|numeric|between:0,200',
            'temperature_air'   => 'sometimes|nullable|numeric|between:-30,60',
            'humidite_relative' => 'sometimes|nullable|numeric|between:0,100',
            'observations'      => 'sometimes|nullable|string',
        ]);

        $mesure->update($validated);

        return response()->json(['data' => $mesure->fresh(), 'message' => 'Mesure mise à jour']);
    }

    public function destroy(MesureAir $mesure): JsonResponse
    {
        $mesure->delete();
        return response()->json(['message' => 'Mesure supprimée']);
    }

        public function valider(MesureAir $mesure): JsonResponse
    {
        $mesure->update([
            'valide'      => true,
            'validee_par' => Auth::id(),
        ]);

        return response()->json(['data' => $mesure, 'message' => 'Mesure validée']);
    }

    public function statistiquesSite(int $siteId): JsonResponse
    {
        $mesures = MesureAir::where('site_id', $siteId)
            ->where('date_mesure', '>=', now()->subDays(30))
            ->get();

        return response()->json(['data' => [
            'evolution_pm25' => $mesures->map(fn ($m) => ['date' => $m->date_mesure->format('d/m'), 'v' => $m->pm25])->filter(fn ($m) => $m['v'])->values(),
            'evolution_pm10' => $mesures->map(fn ($m) => ['date' => $m->date_mesure->format('d/m'), 'v' => $m->pm10])->filter(fn ($m) => $m['v'])->values(),
            'evolution_iqa'  => $mesures->map(fn ($m) => ['date' => $m->date_mesure->format('d/m'), 'v' => $m->iqa])->filter(fn ($m) => $m['v'])->values(),
            'gaz' => [
                'co2' => round($mesures->avg('co2'), 1),
                'no2' => round($mesures->avg('no2'), 1),
                'so2' => round($mesures->avg('so2'), 1),
                'o3'  => round($mesures->avg('o3'), 1),
            ],
            'iqa_moyen'   => round($mesures->avg('iqa'), 0),
            'bruit_moyen' => round($mesures->avg('niveau_bruit_db'), 1),
        ]]);
    }
}
