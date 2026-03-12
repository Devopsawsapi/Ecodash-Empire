<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneInfluence extends Model
{
    protected $fillable = [
        'site_id', 'nom_zone', 'population_estimee', 'nb_menages',
        'groupes_vulnerables', 'langue_principale', 'description',
    ];

    protected $casts = [
        'groupes_vulnerables' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function plaintes()
    {
        return $this->hasMany(Plainte::class, 'zone_id');
    }
}
