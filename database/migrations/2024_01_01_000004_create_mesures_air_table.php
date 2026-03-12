<?php
// ================================================================
// MIGRATION 004 — MODULE AIR
// ================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mesures_air', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();

            // --- Particules ---
            $table->decimal('pm25', 7, 2)->nullable()->comment('µg/m³ — PM2.5 < 15 OMS');
            $table->decimal('pm10', 7, 2)->nullable()->comment('µg/m³ — PM10 < 45 OMS');
            $table->decimal('pm1', 7, 2)->nullable()->comment('µg/m³');
            $table->decimal('tsp', 7, 2)->nullable()->comment('µg/m³ — Total Suspended Particles');

            // --- Gaz (µg/m³ ou ppm) ---
            $table->decimal('co2', 8, 2)->nullable()->comment('ppm — < 1000 ppm intérieur');
            $table->decimal('co', 7, 3)->nullable()->comment('mg/m³ — CO < 4 mg/m³');
            $table->decimal('no2', 7, 2)->nullable()->comment('µg/m³ — NO2 < 25 OMS');
            $table->decimal('so2', 7, 2)->nullable()->comment('µg/m³ — SO2 < 40 OMS');
            $table->decimal('o3', 7, 2)->nullable()->comment('µg/m³ — O3 < 100 OMS');
            $table->decimal('nh3', 7, 3)->nullable()->comment('ppm — Ammoniaque');
            $table->decimal('h2s', 7, 4)->nullable()->comment('ppm — H2S < 0.02 ppm');
            $table->decimal('voc', 7, 3)->nullable()->comment('ppm — COV totaux');

            // --- Bruit ---
            $table->decimal('niveau_bruit_db', 5, 1)->nullable()->comment('dB(A) — < 55 dB jour OMS');
            $table->enum('periode_bruit', ['jour', 'nuit', 'mixte'])->nullable();

            // --- Conditions météo ---
            $table->decimal('temperature_air', 5, 2)->nullable()->comment('°C');
            $table->decimal('humidite_relative', 5, 2)->nullable()->comment('%');
            $table->decimal('pression_atm', 7, 2)->nullable()->comment('hPa');
            $table->decimal('vitesse_vent', 5, 2)->nullable()->comment('m/s');
            $table->string('direction_vent', 10)->nullable()->comment('N,NE,E,SE,S,SO,O,NO');
            $table->decimal('rayonnement_solaire', 7, 2)->nullable()->comment('W/m²');

            // --- Indice qualité air (IQA) ---
            $table->integer('iqa')->nullable()->comment('0-500: Bon<50, Moyen<100, Mauvais>200');
            $table->enum('categorie_iqa', ['bon', 'modere', 'mauvais_groupes_sensibles', 'mauvais', 'tres_mauvais', 'dangereux'])->nullable();
            $table->enum('statut_global', ['bon', 'modere', 'mauvais', 'critique'])->default('bon');
            $table->json('polluants_dominants')->nullable();

            $table->text('observations')->nullable();
            $table->string('photo_site')->nullable();
            $table->enum('methode_mesure', ['capteur_fixe', 'capteur_portable', 'laboratoire', 'station_meteo'])->default('capteur_portable');
            $table->timestamp('date_mesure');
            $table->boolean('valide')->default(false);
            $table->foreignId('validee_par')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('mesures_air'); }
};
