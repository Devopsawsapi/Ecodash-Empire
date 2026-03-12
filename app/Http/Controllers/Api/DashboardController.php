<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{MesureAir, MesureEau, MesureSol, PgesAction, Plainte, Site};
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $sites   = Site::actif()->with(['derniereEau', 'dernierAir', 'dernierSol'])->get();
        $statuts = $sites->map(fn ($s) => $s->statut_global);

        return response()->json(['data' => [

            // KPIs globaux
            'total_sites'     => $sites->count(),
            'sites_conformes' => $statuts->filter(fn ($s) => $s === 'conforme')->count(),
            'sites_attention' => $statuts->filter(fn ($s) => $s === 'attention')->count(),
            'sites_critiques' => $statuts->filter(fn ($s) => $s === 'critique')->count(),

            // Module EAU
            'eau' => [
                'mesures_aujourd_hui' => MesureEau::whereDate('date_prelevement', today())->count(),
                'sites_critiques'     => MesureEau::whereDate('date_prelevement', '>=', now()->subDays(7))
                    ->where('statut_global', 'critique')
                    ->distinct('site_id')
                    ->count('site_id'),
                'alertes_actives'     => MesureEau::whereDate('date_prelevement', today())
                    ->whereIn('statut_global', ['attention', 'critique'])
                    ->count(),
                'moyenne_ph_7j'       => MesureEau::where('date_prelevement', '>=', now()->subDays(7))->avg('ph'),
                'moyenne_iqa_7j'      => MesureEau::where('date_prelevement', '>=', now()->subDays(7))->avg('indice_qualite'),
            ],

            // Module AIR
            'air' => [
                'mesures_aujourd_hui' => MesureAir::whereDate('date_mesure', today())->count(),
                'iqa_moyen'           => MesureAir::whereDate('date_mesure', '>=', now()->subDays(7))->avg('iqa'),
                'sites_mauvais_air'   => MesureAir::whereDate('date_mesure', '>=', now()->subDays(3))
                    ->whereIn('statut_global', ['mauvais', 'critique'])
                    ->distinct('site_id')
                    ->count('site_id'),
                'pm25_moyen'          => MesureAir::whereDate('date_mesure', '>=', now()->subDays(7))->avg('pm25'),
            ],

            // Module SOL
            'sol' => [
                'mesures_mois'         => MesureSol::where('date_prelevement', '>=', now()->subDays(30))->count(),
                'sites_degrades'       => MesureSol::where('date_prelevement', '>=', now()->subDays(30))
                    ->whereIn('statut_global', ['degrade', 'critique'])
                    ->distinct('site_id')
                    ->count('site_id'),
                'contaminations_actives' => MesureSol::where('date_prelevement', '>=', now()->subDays(30))
                    ->where('statut_global', 'critique')
                    ->count(),
            ],

            // Module SOCIAL
            'social' => [
                'plaintes_total'       => Plainte::count(),
                'plaintes_en_cours'    => Plainte::parStatut('en_cours')->count(),
                'plaintes_en_retard'   => Plainte::parStatut('en_retard')->count(),
                'plaintes_resolues'    => Plainte::parStatut('resolue')->count(),
                'pges_conformes'       => PgesAction::where('conformite', 'conforme')->count(),
                'pges_non_conformes'   => PgesAction::where('conformite', 'non_conforme')->count(),
                'taux_conformite_pges' => PgesAction::count() > 0
                    ? round(PgesAction::where('conformite', 'conforme')->count() / PgesAction::count() * 100, 1)
                    : 0,
            ],

            // Carte des sites
            'sites_map' => $sites->map(fn ($s) => [
                'id'            => $s->id,
                'nom'           => $s->nom,
                'latitude'      => $s->latitude,
                'longitude'     => $s->longitude,
                'type'          => $s->type_site,
                'statut_global' => $s->statut_global,
                'modules'       => $s->modules_actifs,
                'eau_statut'    => $s->derniereEau?->statut_global,
                'air_statut'    => $s->dernierAir?->statut_global,
                'sol_statut'    => $s->dernierSol?->statut_global,
            ]),
        ]]);
    }
}
