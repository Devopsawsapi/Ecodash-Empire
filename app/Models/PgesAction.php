<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PgesAction extends Model
{
    protected $fillable = [
        'site_id', 'responsable_id', 'code_action', 'titre', 'description',
        'module', 'type_mesure', 'impact_cible', 'phase_projet', 'statut',
        'taux_realisation', 'budget_prevu', 'budget_realise', 'indicateur_performance',
        'valeur_cible', 'valeur_actuelle', 'conformite', 'date_debut_prevue',
        'date_fin_prevue', 'date_realisation', 'observations', 'documents',
    ];

    protected $casts = [
        'documents'         => 'array',
        'taux_realisation'  => 'decimal:2',
        'date_debut_prevue' => 'date',
        'date_fin_prevue'   => 'date',
        'date_realisation'  => 'date',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
