<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\EauController;
use App\Http\Controllers\Api\AirController;
use App\Http\Controllers\Api\SolController;
use App\Http\Controllers\Api\PlainteController;
use App\Http\Controllers\Api\PgesController;
use App\Http\Controllers\Api\BiodiversiteController;
use App\Http\Controllers\Api\RapportController;
use App\Http\Controllers\Api\IndicateursEmploiController;
use App\Http\Controllers\Api\IndicateursSanteController;
use App\Http\Controllers\Api\ComparaisonController;
use App\Http\Controllers\Api\AIController;
use Illuminate\Support\Facades\Route;

/*
|─────────────────────────────────────────────────────────────────
| EcoDash API Routes
| Fichier : routes/api.php
|─────────────────────────────────────────────────────────────────
*/

// ═══════════════════════════════════════════════════════════════
// ROUTES PUBLIQUES (sans authentification)
// ═══════════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
    // Note: register est réservé aux admins — voir routes protégées
});

// Santé de l'API
Route::get('health', fn() => response()->json(['status' => 'ok', 'app' => 'EcoDash', 'version' => '2.0']));

// ═══════════════════════════════════════════════════════════════
// ROUTES PROTÉGÉES (authentification requise)
// ═══════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── AUTHENTIFICATION ──────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::post('logout-all',      [AuthController::class, 'logoutAll']);
        Route::get ('me',              [AuthController::class, 'me']);
        Route::put ('profile',         [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    // ── GESTION UTILISATEURS (Admin seulement) ────────────────
    Route::prefix('users')->group(function () {
        Route::get   ('',                  [UserController::class, 'index']);
        Route::post  ('',                  [UserController::class, 'store']);
        Route::get   ('{user}',            [UserController::class, 'show']);
        Route::put   ('{user}',            [UserController::class, 'update']);
        Route::delete('{user}',            [UserController::class, 'destroy']);
        Route::patch ('{user}/toggle',     [UserController::class, 'toggleActif']);
        Route::post  ('{user}/reset-pass', [UserController::class, 'resetPassword']);
    });

    // ── DASHBOARD ─────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ── SITES ─────────────────────────────────────────────────
    Route::apiResource('sites', SiteController::class);
    Route::get('sites/{site}/statut', [SiteController::class, 'statut']);

    // ── MODULE EAU ────────────────────────────────────────────
    Route::prefix('eau')->group(function () {
        Route::get ('',               [EauController::class, 'index']);
        Route::post('',               [EauController::class, 'store']);
        Route::get ('{mesure}',       [EauController::class, 'show']);
        Route::put ('{mesure}',       [EauController::class, 'update']);
        Route::delete('{mesure}',     [EauController::class, 'destroy']);
        Route::post('{mesure}/valider',[EauController::class, 'valider']);
    });

    // ── MODULE AIR ────────────────────────────────────────────
    Route::prefix('air')->group(function () {
        Route::get ('',               [AirController::class, 'index']);
        Route::post('',               [AirController::class, 'store']);
        Route::get ('{mesure}',       [AirController::class, 'show']);
        Route::put ('{mesure}',       [AirController::class, 'update']);
        Route::delete('{mesure}',     [AirController::class, 'destroy']);
        Route::post('{mesure}/valider',[AirController::class, 'valider']);
    });

    // ── MODULE SOL ────────────────────────────────────────────
    Route::prefix('sol')->group(function () {
        Route::get ('',               [SolController::class, 'index']);
        Route::post('',               [SolController::class, 'store']);
        Route::get ('{mesure}',       [SolController::class, 'show']);
        Route::put ('{mesure}',       [SolController::class, 'update']);
        Route::delete('{mesure}',     [SolController::class, 'destroy']);
        Route::post('{mesure}/valider',[SolController::class, 'valider']);
    });

    // ── MODULE SOCIAL (Plaintes & PGES) ───────────────────────
    Route::prefix('social')->group(function () {
        // Plaintes
        Route::get ('plaintes',                  [PlainteController::class, 'index']);
        Route::post('plaintes',                  [PlainteController::class, 'store']);
        Route::get ('plaintes/{plainte}',        [PlainteController::class, 'show']);
        Route::put ('plaintes/{plainte}',        [PlainteController::class, 'update']);
        Route::delete('plaintes/{plainte}',      [PlainteController::class, 'destroy']);
        Route::post('plaintes/{plainte}/statut', [PlainteController::class, 'changerStatut']);

        // PGES Actions
        Route::get ('pges',                  [PgesController::class, 'index']);
        Route::post('pges',                  [PgesController::class, 'store']);
        Route::get ('pges/{action}',         [PgesController::class, 'show']);
        Route::put ('pges/{action}',         [PgesController::class, 'update']);
        Route::delete('pges/{action}',       [PgesController::class, 'destroy']);

        // Indicateurs emploi
        Route::get ('emploi',            [IndicateursEmploiController::class, 'index']);
        Route::post('emploi',            [IndicateursEmploiController::class, 'store']);
        Route::get ('emploi/{indicateur}',[IndicateursEmploiController::class, 'show']);
        Route::put ('emploi/{indicateur}',[IndicateursEmploiController::class, 'update']);

        // Indicateurs santé
        Route::get ('sante',             [IndicateursSanteController::class, 'index']);
        Route::post('sante',             [IndicateursSanteController::class, 'store']);
        Route::get ('sante/{indicateur}',[IndicateursSanteController::class, 'show']);
        Route::put ('sante/{indicateur}',[IndicateursSanteController::class, 'update']);
    });

    // ── MODULE BIODIVERSITÉ ───────────────────────────────────
    Route::prefix('biodiversite')->group(function () {
        Route::get ('',                 [BiodiversiteController::class, 'index']);
        Route::post('',                 [BiodiversiteController::class, 'store']);
        Route::get ('{observation}',    [BiodiversiteController::class, 'show']);
        Route::put ('{observation}',    [BiodiversiteController::class, 'update']);
        Route::delete('{observation}',  [BiodiversiteController::class, 'destroy']);
    });

    // ── RAPPORTS ──────────────────────────────────────────────
    Route::prefix('rapports')->group(function () {
        Route::get('',        [RapportController::class, 'index']);
        Route::post('',       [RapportController::class, 'generer']);
        Route::get('{id}',    [RapportController::class, 'show']);
        Route::delete('{id}', [RapportController::class, 'destroy']);
    });

    // ── COMPARAISON MULTI-SOURCES ─────────────────────────────
    Route::prefix('comparaison')->group(function () {
        // Air
        Route::get ('air/{id}',          [ComparaisonController::class, 'comparerAir']);
        Route::post('air/analyse',        [ComparaisonController::class, 'analyserAir']);
        // Eau
        Route::get ('eau/{id}',          [ComparaisonController::class, 'comparerEau']);
        Route::post('eau/analyse',        [ComparaisonController::class, 'analyserEau']);
        // Sol
        Route::get ('sol/{id}',          [ComparaisonController::class, 'comparerSol']);
        Route::post('sol/analyse',        [ComparaisonController::class, 'analyserSol']);
        // Biodiversité
        Route::get ('biodiversite/{id}', [ComparaisonController::class, 'comparerBiodiversite']);
        // Social
        Route::get ('social',            [ComparaisonController::class, 'comparerSocial']);
        // Rapport site complet
        Route::get ('site/{id}',         [ComparaisonController::class, 'rapportSite']);
    });

    // ── INTELLIGENCE ARTIFICIELLE ─────────────────────────────
    Route::prefix('ia')->group(function () {
        // Analyse IA d'une mesure vs normes internationales
        Route::post('analyser',   [AIController::class, 'analyser']);
        // Prédiction tendances sur historique
        Route::post('predire',    [AIController::class, 'predire']);
        // Alertes intelligentes multi-modules
        Route::get ('alertes',    [AIController::class, 'alertes']);
        Route::post('alertes',    [AIController::class, 'alertes']);
        // Rapport narratif PDF-ready
        Route::post('rapport',    [AIController::class, 'rapport']);
        // Chatbot IA environnemental
        Route::post('chat',       [AIController::class, 'chat']);
        // Analyse complète d'un site
        Route::get ('site/{id}',  [AIController::class, 'analyseSite']);
    });

});
