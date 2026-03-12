<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// ════════════════════════════════════════════════════════════════
// EcoDashSeeder — Crée les utilisateurs par défaut
// Commande : php artisan db:seed --class=EcoDashSeeder
// ════════════════════════════════════════════════════════════════

class EcoDashSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // ── ADMINISTRATEUR PRINCIPAL ──────────────────────────
            [
                'name'             => 'Admin EcoDash',
                'email'            => 'admin@ecodash.com',
                'password'         => Hash::make('Admin@2026!'),   // ← CHANGEZ CE MOT DE PASSE
                'telephone'        => '+242 06 000 0001',
                'matricule'        => 'ECO-ADM-0001',
                'role'             => 'admin',
                'modules_acces'    => ['eau', 'air', 'sol', 'social', 'biodiversite'],
                'zone_affectation' => 'National',
                'actif'            => true,
            ],

            // ── INGÉNIEUR ENVIRONNEMENT ───────────────────────────
            [
                'name'             => 'Jean Mulumba',
                'email'            => 'j.mulumba@ecodash.com',
                'password'         => Hash::make('Ecodash@2026'),
                'telephone'        => '+242 06 100 0002',
                'matricule'        => 'ECO-ING-0001',
                'role'             => 'ingenieur_env',
                'modules_acces'    => ['eau', 'air', 'sol'],
                'zone_affectation' => 'Pointe-Noire',
                'actif'            => true,
            ],

            // ── INGÉNIEUR SOCIAL ──────────────────────────────────
            [
                'name'             => 'Marie Kabila',
                'email'            => 'm.kabila@ecodash.com',
                'password'         => Hash::make('Ecodash@2026'),
                'telephone'        => '+242 06 200 0003',
                'matricule'        => 'ECO-ING-0002',
                'role'             => 'ingenieur_social',
                'modules_acces'    => ['social', 'biodiversite'],
                'zone_affectation' => 'Brazzaville',
                'actif'            => true,
            ],

            // ── TECHNICIEN EAU ────────────────────────────────────
            [
                'name'             => 'Pierre Tshimanga',
                'email'            => 'p.tshimanga@ecodash.com',
                'password'         => Hash::make('Ecodash@2026'),
                'telephone'        => '+242 06 300 0004',
                'matricule'        => 'ECO-TECH-0001',
                'role'             => 'technicien_eau',
                'modules_acces'    => ['eau'],
                'zone_affectation' => 'Kouilou',
                'actif'            => true,
            ],

            // ── TECHNICIEN SOL ────────────────────────────────────
            [
                'name'             => 'Amina Mwanza',
                'email'            => 'a.mwanza@ecodash.com',
                'password'         => Hash::make('Ecodash@2026'),
                'telephone'        => '+242 06 400 0005',
                'matricule'        => 'ECO-TECH-0002',
                'role'             => 'technicien_sol',
                'modules_acces'    => ['sol'],
                'zone_affectation' => 'Niari',
                'actif'            => true,
            ],

            // ── OBSERVATEUR ───────────────────────────────────────
            [
                'name'             => 'David Lumumba',
                'email'            => 'd.lumumba@ecodash.com',
                'password'         => Hash::make('Ecodash@2026'),
                'telephone'        => '',
                'matricule'        => 'ECO-OBS-0001',
                'role'             => 'observateur',
                'modules_acces'    => ['biodiversite'],
                'zone_affectation' => 'Mayombe',
                'actif'            => false,  // Désactivé par défaut
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('✅ ' . count($users) . ' utilisateurs créés/mis à jour.');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('  COMPTE ADMINISTRATEUR PRINCIPAL');
        $this->command->info('  Email    : admin@ecodash.com');
        $this->command->info('  Password : Admin@2026!');
        $this->command->info('  ⚠️  CHANGEZ CE MOT DE PASSE EN PRODUCTION !');
        $this->command->info('═══════════════════════════════════════════');
    }
}