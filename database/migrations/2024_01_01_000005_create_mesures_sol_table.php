<?php
// ================================================================
// MIGRATION 005 — MODULE SOL
// ================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mesures_sol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();

            // --- Caractéristiques physiques ---
            $table->decimal('ph_sol', 4, 2)->nullable()->comment('5.5-7.5 agricole');
            $table->decimal('humidite_sol', 5, 2)->nullable()->comment('% massique');
            $table->decimal('matiere_organique', 5, 2)->nullable()->comment('% — > 2% bon');
            $table->decimal('carbone_organique', 5, 2)->nullable()->comment('% COT');
            $table->decimal('azote_total', 6, 3)->nullable()->comment('% — N total');
            $table->decimal('phosphore_dispo', 7, 2)->nullable()->comment('mg/kg');
            $table->decimal('potassium_echangeable', 7, 2)->nullable()->comment('méq/100g');
            $table->decimal('capacite_echange', 6, 2)->nullable()->comment('CEC méq/100g');
            $table->enum('texture', ['argileuse', 'limoneuse', 'sableuse', 'argilo_limoneuse', 'franco_sableuse', 'franche'])->nullable();
            $table->decimal('conductivite_sol', 7, 3)->nullable()->comment('dS/m — salinité');
            $table->decimal('densite_apparente', 5, 3)->nullable()->comment('g/cm³');
            $table->decimal('profondeur_prelevement', 5, 1)->nullable()->comment('cm');

            // --- Métaux lourds sol (mg/kg MS) ---
            $table->decimal('plomb_sol', 8, 3)->nullable()->comment('Pb mg/kg — < 100');
            $table->decimal('mercure_sol', 8, 4)->nullable()->comment('Hg mg/kg — < 1');
            $table->decimal('arsenic_sol', 8, 3)->nullable()->comment('As mg/kg — < 40');
            $table->decimal('cadmium_sol', 8, 4)->nullable()->comment('Cd mg/kg — < 2');
            $table->decimal('chrome_sol', 8, 3)->nullable()->comment('Cr mg/kg — < 150');
            $table->decimal('nickel_sol', 8, 3)->nullable()->comment('Ni mg/kg — < 70');
            $table->decimal('zinc_sol', 8, 3)->nullable()->comment('Zn mg/kg — < 300');
            $table->decimal('cuivre_sol', 8, 3)->nullable()->comment('Cu mg/kg — < 100');

            // --- Contamination organique ---
            $table->decimal('hydrocarbures_totaux', 8, 3)->nullable()->comment('mg/kg — HTH');
            $table->decimal('pesticides_totaux', 8, 4)->nullable()->comment('mg/kg');
            $table->decimal('pcb_totaux', 8, 5)->nullable()->comment('mg/kg');
            $table->boolean('presence_huile')->default(false);

            // --- Biologie du sol ---
            $table->decimal('activite_microbienne', 7, 2)->nullable()->comment('µg CO2/g/h');
            $table->integer('vers_de_terre_m2')->nullable()->comment('biodiversité sol');

            // --- Érosion et dégradation ---
            $table->enum('niveau_erosion', ['nul', 'faible', 'modere', 'fort', 'tres_fort'])->nullable();
            $table->boolean('presence_ravinement')->default(false);
            $table->decimal('taux_couverture_vegetale', 5, 2)->nullable()->comment('% couverture');

            // --- Usage du sol ---
            $table->enum('usage_sol', ['agricole', 'forestier', 'industriel', 'residentiel', 'friche', 'minier'])->nullable();
            $table->enum('statut_global', ['sain', 'attention', 'degrade', 'critique'])->default('sain');
            $table->json('anomalies')->nullable();
            $table->decimal('indice_qualite_sol', 5, 2)->nullable()->comment('IQS 0-100');

            $table->text('observations')->nullable();
            $table->json('photos')->nullable();
            $table->string('coordonnees_gps')->nullable();
            $table->timestamp('date_prelevement');
            $table->boolean('valide')->default(false);
            $table->foreignId('validee_par')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('mesures_sol'); }
};
