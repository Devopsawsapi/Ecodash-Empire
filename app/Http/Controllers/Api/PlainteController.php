<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plainte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlainteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Plainte::with(['site', 'agent'])
            ->when($request->statut,    fn ($q, $s) => $q->where('statut', $s))
            ->when($request->categorie, fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->type,      fn ($q, $t) => $q->where('type', $t))
            ->when($request->priorite,  fn ($q, $p) => $q->where('priorite', $p))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'declarant_nom'       => 'required|string|max:255',
            'declarant_telephone' => 'nullable|string',
            'declarant_anonyme'   => 'nullable|boolean',
            'declarant_genre'     => 'nullable|in:homme,femme,non_precise',
            'declarant_groupe'    => 'nullable|in:resident,agriculteur,pecheur,employe,chef_communaute,autre',
            'site_id'             => 'nullable|exists:sites,id',
            'categorie'           => 'required|in:environnement,social,economique,securite,sante,patrimoine,autre',
            'type'                => 'required|string',
            'sujet'               => 'required|string|max:255',
            'description'         => 'required|string',
            'latitude'            => 'nullable|numeric',
            'longitude'           => 'nullable|numeric',
            'priorite'            => 'nullable|in:faible,normale,haute,urgente,critique',
            'photos.*'            => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('photos')) {
            $validated['photos'] = collect($request->file('photos'))
                ->map(fn ($p) => $p->store('plaintes/photos', 'public'))
                ->toArray();
        }

        $plainte = Plainte::create($validated);

        return response()->json([
            'data'      => $plainte,
            'message'   => 'Grief enregistré',
            'reference' => $plainte->reference,
        ], 201);
    }

    public function show(Plainte $plainte): JsonResponse
    {
        return response()->json(['data' => $plainte->load(['site', 'agent', 'zone'])]);
    }

    public function update(Plainte $plainte, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut'            => 'sometimes|string',
            'priorite'          => 'sometimes|string',
            'assigne_a'         => 'sometimes|nullable|exists:users,id',
            'notes_internes'    => 'sometimes|nullable|string',
            'reponse_declarant' => 'sometimes|nullable|string',
            'note_satisfaction' => 'sometimes|nullable|integer|between:1,5',
        ]);

        if (isset($validated['statut']) && $validated['statut'] === 'resolue') {
            $validated['date_resolution'] = now();
        }

        $plainte->update($validated);

        return response()->json(['data' => $plainte, 'message' => 'Plainte mise à jour']);
    }

    public function destroy(Plainte $plainte): JsonResponse
    {
        $plainte->delete();
        return response()->json(['message' => 'Plainte supprimée']);
    }

    public function changerStatut(Request $request, Plainte $plainte): JsonResponse
    {
        $request->validate([
            'statut'            => 'required|in:soumise,en_cours,en_attente,resolue,rejetee,fermee',
            'notes_internes'    => 'nullable|string',
            'reponse_declarant' => 'nullable|string',
        ]);

        $data = ['statut' => $request->statut];
        if ($request->notes_internes)    $data['notes_internes']    = $request->notes_internes;
        if ($request->reponse_declarant) $data['reponse_declarant'] = $request->reponse_declarant;
        if ($request->statut === 'resolue') $data['date_resolution'] = now();

        $plainte->update($data);

        return response()->json(['data' => $plainte->fresh(), 'message' => 'Statut mis à jour']);
    }

        public function statistiques(): JsonResponse
    {
        return response()->json(['data' => [
            'total'         => Plainte::count(),
            'par_statut'    => Plainte::selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut'),
            'par_categorie' => Plainte::selectRaw('categorie, count(*) as total')->groupBy('categorie')->pluck('total', 'categorie'),
            'par_type'      => Plainte::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'taux_resolution'     => Plainte::count() > 0
                ? round(Plainte::where('statut', 'resolue')->count() / Plainte::count() * 100, 1)
                : 0,
            'satisfaction_moyenne' => round(Plainte::whereNotNull('note_satisfaction')->avg('note_satisfaction'), 1),
            'recentes'             => Plainte::with('site')->orderBy('created_at', 'desc')->limit(10)->get(),
        ]]);
    }
}
