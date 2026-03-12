<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicateurSante extends Model
{
    protected $table = 'indicateurs_sante';

    protected $fillable = [
        'site_id', 'zone_id', 'agent_id', 'population_enquetee',
        'nb_maladies_respiratoires', 'nb_maladies_diarrheeiques', 'nb_maladies_peau',
        'taux_acces_eau_potable', 'taux_acces_assainissement',
        'periode', 'annee', 'trimestre', 'observations',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function zone()
    {
        return $this->belongsTo(ZoneInfluence::class, 'zone_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
