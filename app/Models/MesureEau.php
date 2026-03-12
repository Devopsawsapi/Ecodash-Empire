<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesureEau extends Model
{
    protected $table = 'mesures_eau';

    protected $fillable = [
        'site_id', 'technicien_id', 'ph', 'turbidite', 'oxygene_dissous', 'conductivite',
        'temperature_eau', 'tds', 'nitrates', 'dbo5', 'dco', 'plomb', 'mercure', 'arsenic',
        'cadmium', 'chrome', 'zinc', 'fer', 'manganese', 'coliformes_totaux', 'e_coli',
        'enterococcus', 'type_source', 'usage', 'statut_global', 'anomalies', 'indice_qualite',
        'observations', 'photo_prelevement', 'date_prelevement', 'valide', 'validee_par', 'validee_le',
    ];

    protected $casts = [
        'date_prelevement' => 'datetime',
        'anomalies'        => 'array',
        'valide'           => 'boolean',
        'validee_le'       => 'datetime',
    ];

    // Seuils OMS / normes eau potable
    const SEUILS = [
        'ph'                => ['min' => 6.5, 'max' => 8.5, 'critere' => 'both'],
        'turbidite'         => ['max' => 5,   'critere' => 'max'],
        'oxygene_dissous'   => ['min' => 5.0, 'critere' => 'min'],
        'nitrates'          => ['max' => 50,  'critere' => 'max'],
        'dbo5'              => ['max' => 5,   'critere' => 'max'],
        'plomb'             => ['max' => 0.01,  'critere' => 'max', 'critical' => true],
        'mercure'           => ['max' => 0.001, 'critere' => 'max', 'critical' => true],
        'arsenic'           => ['max' => 0.01,  'critere' => 'max', 'critical' => true],
        'cadmium'           => ['max' => 0.003, 'critere' => 'max', 'critical' => true],
        'coliformes_totaux' => ['max' => 0, 'critere' => 'max'],
        'e_coli'            => ['max' => 0, 'critere' => 'max', 'critical' => true],
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($mesure) {
            $mesure->calculerStatut();
        });
    }

    protected function calculerStatut(): void
    {
        $anomalies = [];
        $critique  = false;
        $attention = false;

        foreach (self::SEUILS as $param => $seuil) {
            $valeur = $this->$param;
            if ($valeur === null) {
                continue;
            }

            $hors = false;
            if (isset($seuil['min']) && $valeur < $seuil['min']) {
                $hors = true;
            }
            if (isset($seuil['max']) && $valeur > $seuil['max']) {
                $hors = true;
            }

            if ($hors) {
                $anomalies[] = $param;
                if (!empty($seuil['critical'])) {
                    $critique = true;
                } else {
                    $attention = true;
                }
            }
        }

        $this->anomalies    = $anomalies;
        $this->statut_global = $critique ? 'critique' : ($attention ? 'attention' : 'conforme');

        // Calcul IQE simple
        $score              = 100 - (count($anomalies) * 15);
        $this->indice_qualite = max(0, min(100, $score));
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

    // ── Scopes ────────────────────────────────────────────────

    public function scopeValide($query)
    {
        return $query->where('valide', true);
    }

    public function scopeRecent($query, int $jours = 30)
    {
        return $query->where('date_prelevement', '>=', now()->subDays($jours));
    }
}
