<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationBiodiversite extends Model
{
    protected $table = 'observations_biodiversite';

    protected $fillable = [
        'site_id', 'observateur_id', 'compartiment', 'nom_espece', 'nom_scientifique',
        'nombre_individus', 'statut_iucn', 'espece_endemique', 'espece_invasive',
        'tendance_population', 'habitat_observe', 'photos', 'latitude_obs', 'longitude_obs',
        'date_observation', 'notes',
    ];

    protected $casts = [
        'photos'           => 'array',
        'date_observation' => 'datetime',
        'espece_endemique' => 'boolean',
        'espece_invasive'  => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function observateur()
    {
        return $this->belongsTo(User::class, 'observateur_id');
    }
}
