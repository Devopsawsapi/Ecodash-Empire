<?php
// ================================================================
// MIGRATION 002 — SITES & ZONES
// ================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Sites physiques de mesure
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('localite');
            $table->string('province')->nullable();
            $table->string('pays')->default('RDC');
            $table->enum('type_site', ['industriel', 'agricole', 'urbain', 'rural', 'minier', 'fluvial', 'forestier']);
            $table->json('modules_actifs')->nullable()->comment('["eau","air","sol","social"]');
            $table->string('photo')->nullable();
            $table->boolean('actif')->default(true);
            $table->json('responsables')->nullable()->comment('IDs des techniciens responsables');
            $table->timestamps();
            $table->softDeletes();
        });

        // Zones communautaires d'influence
        Schema::create('zones_influence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('nom_zone');
            $table->integer('population_estimee')->nullable();
            $table->integer('nb_menages')->nullable();
            $table->json('groupes_vulnerables')->nullable()->comment('["enfants","femmes_enceintes","personnes_agees"]');
            $table->string('langue_principale')->default('Lingala');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('zones_influence');
        Schema::dropIfExists('sites');
    }
};
