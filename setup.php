<?php

/**
 * EcoDash — setup.php
 * Script de démarrage Railway (Laravel 12 compatible)
 * ─────────────────────────────────────────────────
 * Lancé par : php setup.php
 * Puis       : php artisan serve --host=0.0.0.0 --port=$PORT
 */

echo "\n╔══════════════════════════════════════════╗\n";
echo   "║   EcoDash v2 — Empire TechNova           ║\n";
echo   "║   Initialisation Railway                 ║\n";
echo   "╚══════════════════════════════════════════╝\n\n";

// ── Bootstrap complet de Laravel 12 ───────────────────────────
require __DIR__ . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/bootstrap/app.php';

// Laravel 12 : bootstrap via le Kernel console
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── Helper ────────────────────────────────────────────────────
function artisan(string $cmd, array $args = []): void
{
    echo "▸ $cmd ...";
    try {
        \Illuminate\Support\Facades\Artisan::call($cmd, $args);
        $out = trim(\Illuminate\Support\Facades\Artisan::output());
        echo " ✓\n";
        if ($out) {
            foreach (explode("\n", $out) as $line) {
                if (trim($line)) echo "  $line\n";
            }
        }
    } catch (\Throwable $e) {
        echo " ⚠  " . $e->getMessage() . "\n";
    }
}

// ── 1. APP_KEY ─────────────────────────────────────────────────
if (empty(env('APP_KEY'))) {
    artisan('key:generate', ['--force' => true]);
} else {
    echo "▸ APP_KEY présente ✓\n";
}

// ── 2. Vider le cache avant migration ─────────────────────────
artisan('config:clear');

// ── 3. Migrations ─────────────────────────────────────────────
echo "\n── Migrations ──────────────────────────────\n";
artisan('migrate', ['--force' => true]);

// ── 4. Seeder (seulement si users est vide) ───────────────────
echo "\n── Données par défaut ──────────────────────\n";
try {
    $count = \App\Models\User::count();
} catch (\Throwable $e) {
    $count = 0;
}

if ($count === 0) {
    artisan('db:seed', [
        '--class' => 'Database\\Seeders\\EcoDashSeeder',
        '--force' => true,
    ]);
} else {
    echo "▸ $count utilisateur(s) déjà en base — seeder ignoré ✓\n";
}

// ── 5. Optimisations production ───────────────────────────────
echo "\n── Optimisations ───────────────────────────\n";
artisan('config:cache');
artisan('route:cache');
artisan('view:cache');
artisan('storage:link');

echo "\n╔══════════════════════════════════════════╗\n";
echo   "║  ✅  Prêt !                               ║\n";
echo   "║  Admin : admin@ecodash.com               ║\n";
echo   "║  Pass  : Admin@2026!                     ║\n";
echo   "║  ⚠   Changez le mot de passe !           ║\n";
echo   "╚══════════════════════════════════════════╝\n\n";