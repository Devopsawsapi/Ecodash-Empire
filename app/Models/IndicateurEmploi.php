<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicateurEmploi extends Model
{
    protected $table = 'indicateurs_emploi';

    protected $fillable = [
        'site_id', 'agent_id', 'emplois_directs_crees', 'emplois_locaux',
        'pct_emplois_femmes', 'pct_emplois_jeunes', 'salaire_moyen_local',
        'fournisseurs_locaux', 'investissement_social', 'projets_sociaux', 'annee', 'observations',
    ];

    protected $casts = [
        'projets_sociaux' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
