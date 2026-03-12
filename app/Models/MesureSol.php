<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesureSol extends Model
{
    protected $table = 'mesures_sol';

    protected $fillable = [
        'site_id', 'technicien_id', 'ph_sol', 'humidite_sol', 'matiere_organique',
        'carbone_organique', 'azote_total', 'phosphore_dispo', 'capacite_echange', 'texture',
        'conductivite_sol', 'profondeur_prelevement', 'plomb_sol', 'mercure_sol', 'arsenic_sol',
        'cadmium_sol', 'chrome_sol', 'nickel_sol', 'zinc_sol', 'cuivre_sol',
        'hydrocarbures_totaux', 'pesticides_totaux', 'pcb_totaux', 'presence_huile',
        'activite_microbienne', 'niveau_erosion', 'taux_couverture_vegetale', 'usage_sol',
        'statut_global', 'anomalies', 'indice_qualite_sol', 'observations', 'photos',
        'date_prelevement', 'valide', 'validee_par',
    ];

    protected $casts = [
        'date_prelevement' => 'datetime',
        'anomalies'        => 'array',
        'photos'           => 'array',
        'valide'           => 'boolean',
        'presence_huile'   => 'boolean',
    ];

    // Seuils réglementaires sol (mg/kg MS)
    const SEUILS_SOL = [
        'plomb_sol'            => ['max' => 100,  'critical' => true],
        'mercure_sol'          => ['max' => 1,    'critical' => true],
        'arsenic_sol'          => ['max' => 40,   'critical' => true],
        'cadmium_sol'          => ['max' => 2,    'critical' => true],
        'hydrocarbures_totaux' => ['max' => 500,  'critical' => true],
        'pesticides_totaux'    => ['max' => 1,    'critical' => false],
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($mesure) {
            $mesure->calculerStatutSol();
        });
    }

    protected function calculerStatutSol(): void
    {
        $anomalies = [];
        $critique  = false;
        $attention = false;

        foreach (self::SEUILS_SOL as $param => $seuil) {
            $valeur = $this->$param;
            if ($valeur === null) {
                continue;
            }
            if ($valeur > $seuil['max']) {
                $anomalies[] = $param;
                if ($seuil['critical']) {
                    $critique = true;
                } else {
                    $attention = true;
                }
            }
        }

        // Critères agronomiques
        if ($this->ph_sol && ($this->ph_sol < 5.0 || $this->ph_sol > 8.5)) {
            $anomalies[] = 'ph_sol';
            $attention = true;
        }
        if ($this->matiere_organique && $this->matiere_organique < 1.0) {
            $anomalies[] = 'matiere_organique';
            $attention = true;
        }
        if ($this->niveau_erosion && in_array($this->niveau_erosion, ['fort', 'tres_fort'])) {
            $anomalies[] = 'erosion';
            $attention = true;
        }

        $this->anomalies          = $anomalies;
        $this->statut_global      = $critique
            ? 'critique'
            : ($attention ? 'degrade' : (count($anomalies) > 0 ? 'attention' : 'sain'));
        $this->indice_qualite_sol = max(0, min(100, 100 - count($anomalies) * 12));
    }

    // ── Relations ─────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function technicien()
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }
}
