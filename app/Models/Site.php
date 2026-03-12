<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nom', 'code', 'description', 'latitude', 'longitude', 'localite',
        'province', 'pays', 'type_site', 'modules_actifs', 'photo', 'actif', 'responsables',
    ];

    protected $casts = [
        'modules_actifs' => 'array',
        'responsables'   => 'array',
        'actif'          => 'boolean',
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
    ];

    // ── Relations ─────────────────────────────────────────────

    public function mesuresEau()
    {
        return $this->hasMany(MesureEau::class);
    }

    public function mesuresAir()
    {
        return $this->hasMany(MesureAir::class);
    }

    public function mesuresSol()
    {
        return $this->hasMany(MesureSol::class);
    }

    public function plaintes()
    {
        return $this->hasMany(Plainte::class);
    }

    public function pgesActions()
    {
        return $this->hasMany(PgesAction::class);
    }

    public function biodiversite()
    {
        return $this->hasMany(ObservationBiodiversite::class);
    }

    public function indicateursSante()
    {
        return $this->hasMany(IndicateurSante::class);
    }

    public function indicateursEmploi()
    {
        return $this->hasMany(IndicateurEmploi::class);
    }

    public function zonesInfluence()
    {
        return $this->hasMany(ZoneInfluence::class);
    }

    public function derniereEau()
    {
        return $this->hasOne(MesureEau::class)->latestOfMany('date_prelevement');
    }

    public function dernierAir()
    {
        return $this->hasOne(MesureAir::class)->latestOfMany('date_mesure');
    }

    public function dernierSol()
    {
        return $this->hasOne(MesureSol::class)->latestOfMany('date_prelevement');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    // ── Accesseurs ────────────────────────────────────────────

    public function getStatutGlobalAttribute(): string
    {
        $statuts = [];

        if ($this->derniereEau) {
            $statuts[] = $this->derniereEau->statut_global;
        }
        if ($this->dernierAir) {
            $statuts[] = $this->dernierAir->statut_global;
        }
        if ($this->dernierSol) {
            $statuts[] = $this->dernierSol->statut_global;
        }

        if (in_array('critique', $statuts)) {
            return 'critique';
        }
        if (in_array('attention', $statuts) || in_array('mauvais', $statuts) || in_array('degrade', $statuts)) {
            return 'attention';
        }

        return empty($statuts) ? 'inconnu' : 'conforme';
    }
}
