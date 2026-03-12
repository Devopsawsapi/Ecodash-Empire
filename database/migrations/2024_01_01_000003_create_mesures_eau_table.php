<?php
// ================================================================
// MIGRATION 003 — MODULE EAU
// ================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mesures_eau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();

            // --- Paramètres physico-chimiques ---
            $table->decimal('ph', 4, 2)->nullable()->comment('6.5-8.5 OMS');
            $table->decimal('turbidite', 8, 2)->nullable()->comment('NTU — < 5 NTU potable');
            $table->decimal('oxygene_dissous', 6, 2)->nullable()->comment('mg/L — > 5 mg/L');
            $table->decimal('conductivite', 8, 2)->nullable()->comment('µS/cm — 200-800');
            $table->decimal('temperature_eau', 5, 2)->nullable()->comment('°C');
            $table->decimal('tds', 8, 2)->nullable()->comment('mg/L — TDS < 500');
            $table->decimal('dureté', 6, 2)->nullable()->comment('mg/L CaCO3');
            $table->decimal('nitrates', 6, 2)->nullable()->comment('mg/L — < 50 OMS');
            $table->decimal('phosphates', 6, 3)->nullable()->comment('mg/L');
            $table->decimal('dbo5', 6, 2)->nullable()->comment('mg/L — DBO5 < 5');
            $table->decimal('dco', 6, 2)->nullable()->comment('mg/L');

            // --- Métaux lourds (mg/L) ---
            $table->decimal('plomb', 8, 5)->nullable()->comment('Pb — < 0.01 OMS');
            $table->decimal('mercure', 8, 6)->nullable()->comment('Hg — < 0.001 OMS');
            $table->decimal('arsenic', 8, 5)->nullable()->comment('As — < 0.01 OMS');
            $table->decimal('cadmium', 8, 6)->nullable()->comment('Cd — < 0.003 OMS');
            $table->decimal('chrome', 8, 5)->nullable()->comment('Cr — < 0.05 OMS');
            $table->decimal('zinc', 8, 4)->nullable()->comment('Zn — < 3 OMS');
            $table->decimal('fer', 8, 4)->nullable()->comment('Fe — < 0.3 OMS');
            $table->decimal('manganese', 8, 5)->nullable()->comment('Mn — < 0.4 OMS');

            // --- Microbiologiques ---
            $table->integer('coliformes_totaux')->nullable()->comment('UFC/100mL — 0');
            $table->integer('e_coli')->nullable()->comment('UFC/100mL — 0');
            $table->integer('enterococcus')->nullable()->comment('UFC/100mL');

            // --- Source d'eau ---
            $table->enum('type_source', ['surface', 'souterraine', 'traitement', 'distribution'])->default('surface');
            $table->enum('usage', ['potable', 'irrigation', 'industriel', 'peche'])->default('potable');

            // --- Statut calculé ---
            $table->enum('statut_global', ['conforme', 'attention', 'critique'])->default('conforme');
            $table->json('anomalies')->nullable();
            $table->decimal('indice_qualite', 5, 2)->nullable()->comment('IQE 0-100');

            $table->text('observations')->nullable();
            $table->string('photo_prelevement')->nullable();
            $table->timestamp('date_prelevement');
            $table->boolean('valide')->default(false);
            $table->foreignId('validee_par')->nullable()->constrained('users');
            $table->timestamp('validee_le')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('mesures_eau'); }
};
