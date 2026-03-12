<?php
// ================================================================
// MIGRATION 006 — MODULE ENVIRONNEMENT SOCIAL
// ================================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        // --- PLAINTES & GRIEFS ---
        Schema::create('plaintes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones_influence')->nullOnDelete();

            // Déclarant
            $table->string('declarant_nom');
            $table->string('declarant_telephone')->nullable();
            $table->string('declarant_email')->nullable();
            $table->string('declarant_quartier')->nullable();
            $table->boolean('declarant_anonyme')->default(false);
            $table->enum('declarant_genre', ['homme', 'femme', 'non_precise'])->default('non_precise');
            $table->enum('declarant_groupe', ['resident', 'agriculteur', 'pecheur', 'employe', 'chef_communaute', 'autre'])->default('resident');

            // Plainte
            $table->enum('categorie', ['environnement', 'social', 'economique', 'securite', 'sante', 'patrimoine', 'autre']);
            $table->enum('type', [
                'pollution_eau', 'pollution_air', 'pollution_sol', 'bruit', 'vibrations',
                'sante_publique', 'perte_emploi', 'perte_moyens_subsistance', 'perte_terres',
                'endommagement_biens', 'conflit_social', 'discrimination', 'violence_gbv',
                'mauvaise_pratique_travail', 'corruption', 'securite', 'biodiversite', 'autre'
            ]);
            $table->string('sujet');
            $table->text('description');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('photos')->nullable();
            $table->json('documents_joints')->nullable();

            // Gestion
            $table->enum('statut', ['recue', 'en_cours', 'en_investigation', 'resolue', 'en_retard', 'rejetee', 'escaladee'])->default('recue');
            $table->enum('priorite', ['faible', 'normale', 'haute', 'urgente', 'critique'])->default('normale');
            $table->boolean('necessite_enquete')->default(false);
            $table->boolean('risque_escalade')->default(false);
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes_internes')->nullable();
            $table->text('reponse_declarant')->nullable();
            $table->boolean('declarant_satisfait')->nullable();
            $table->integer('note_satisfaction')->nullable()->comment('1-5');
            $table->timestamp('date_echeance')->nullable();
            $table->timestamp('date_resolution')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // --- INDICATEURS SANTÉ COMMUNAUTAIRE ---
        Schema::create('indicateurs_sante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('zone_id')->nullable()->constrained('zones_influence')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('population_enquetee');
            $table->integer('nb_maladies_respiratoires')->nullable();
            $table->integer('nb_maladies_diarrheeiques')->nullable();
            $table->integer('nb_maladies_peau')->nullable();
            $table->integer('nb_maladies_neurologiques')->nullable();
            $table->integer('nb_cancers_declares')->nullable();
            $table->integer('nb_fausses_couches')->nullable();
            $table->integer('nb_malformations_congenitales')->nullable();
            $table->decimal('taux_mortalite_infantile', 5, 2)->nullable()->comment('‰');
            $table->decimal('taux_acces_eau_potable', 5, 2)->nullable()->comment('%');
            $table->decimal('taux_acces_assainissement', 5, 2)->nullable()->comment('%');

            $table->enum('periode', ['mensuelle', 'trimestrielle', 'annuelle'])->default('trimestrielle');
            $table->year('annee');
            $table->tinyInteger('trimestre')->nullable();
            $table->integer('mois')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // --- INDICATEURS EMPLOI & ÉCONOMIE ---
        Schema::create('indicateurs_emploi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('emplois_directs_crees')->nullable();
            $table->integer('emplois_indirects_crees')->nullable();
            $table->integer('emplois_locaux')->nullable()->comment('habitants zone influence');
            $table->decimal('pct_emplois_femmes', 5, 2)->nullable();
            $table->decimal('pct_emplois_jeunes', 5, 2)->nullable()->comment('< 35 ans');
            $table->decimal('salaire_moyen_local', 10, 2)->nullable();
            $table->integer('fournisseurs_locaux')->nullable();
            $table->decimal('montant_achats_locaux', 12, 2)->nullable();
            $table->integer('pme_locales_beneficiaires')->nullable();
            $table->decimal('investissement_social', 12, 2)->nullable()->comment('USD — RSE');
            $table->json('projets_sociaux')->nullable()->comment('[{nom, budget, statut}]');

            $table->year('annee');
            $table->tinyInteger('trimestre')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        // --- PGES (Plan de Gestion Environnementale et Sociale) ---
        Schema::create('pges_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('code_action')->unique();
            $table->string('titre');
            $table->text('description');
            $table->enum('module', ['eau', 'air', 'sol', 'social', 'biodiversite', 'patrimoine', 'securite']);
            $table->enum('type_mesure', ['attenuation', 'compensation', 'prevention', 'surveillance', 'renforcement_capacites']);
            $table->string('impact_cible');
            $table->enum('phase_projet', ['preparation', 'construction', 'exploitation', 'fermeture', 'rehabilitation']);

            $table->enum('statut', ['planifiee', 'en_cours', 'realisee', 'reportee', 'annulee', 'non_conforme'])->default('planifiee');
            $table->decimal('taux_realisation', 5, 2)->default(0)->comment('%');
            $table->decimal('budget_prevu', 12, 2)->nullable();
            $table->decimal('budget_realise', 12, 2)->nullable();
            $table->string('indicateur_performance');
            $table->string('valeur_cible');
            $table->string('valeur_actuelle')->nullable();
            $table->enum('conformite', ['conforme', 'partiellement_conforme', 'non_conforme', 'non_applicable'])->default('non_applicable');

            $table->date('date_debut_prevue')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->date('date_realisation')->nullable();
            $table->text('observations')->nullable();
            $table->json('documents')->nullable();
            $table->timestamps();
        });

        // --- BIODIVERSITÉ ---
        Schema::create('observations_biodiversite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('observateur_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('compartiment', ['flore', 'faune_terrestre', 'faune_aquatique', 'avifaune', 'ichtyofaune', 'entomofaune']);
            $table->string('nom_espece');
            $table->string('nom_scientifique')->nullable();
            $table->integer('nombre_individus')->nullable();
            $table->enum('statut_iucn', ['LC', 'NT', 'VU', 'EN', 'CR', 'EW', 'EX', 'DD'])->nullable();
            $table->boolean('espece_endemique')->default(false);
            $table->boolean('espece_invasive')->default(false);
            $table->enum('tendance_population', ['stable', 'en_augmentation', 'en_diminution', 'inconnue'])->default('inconnue');
            $table->text('habitat_observe')->nullable();
            $table->json('photos')->nullable();
            $table->decimal('latitude_obs', 10, 7)->nullable();
            $table->decimal('longitude_obs', 10, 7)->nullable();
            $table->timestamp('date_observation');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('observations_biodiversite');
        Schema::dropIfExists('pges_actions');
        Schema::dropIfExists('indicateurs_emploi');
        Schema::dropIfExists('indicateurs_sante');
        Schema::dropIfExists('plaintes');
    }
};
