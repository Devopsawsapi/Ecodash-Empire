<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plainte extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'site_id', 'zone_id', 'declarant_nom', 'declarant_telephone',
        'declarant_email', 'declarant_quartier', 'declarant_anonyme', 'declarant_genre',
        'declarant_groupe', 'categorie', 'type', 'sujet', 'description', 'latitude',
        'longitude', 'photos', 'documents_joints', 'statut', 'priorite', 'necessite_enquete',
        'risque_escalade', 'assigne_a', 'notes_internes', 'reponse_declarant',
        'declarant_satisfait', 'note_satisfaction', 'date_echeance', 'date_resolution',
    ];

    protected $casts = [
        'photos'              => 'array',
        'documents_joints'    => 'array',
        'declarant_anonyme'   => 'boolean',
        'necessite_enquete'   => 'boolean',
        'risque_escalade'     => 'boolean',
        'declarant_satisfait' => 'boolean',
        'date_echeance'       => 'datetime',
        'date_resolution'     => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plainte) {
            $plainte->reference    = 'GRV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $plainte->date_echeance = match ($plainte->priorite) {
                'critique' => now()->addHours(24),
                'urgente'  => now()->addDays(2),
                'haute'    => now()->addDays(5),
                'normale'  => now()->addDays(14),
                default    => now()->addDays(30),
            };
        });

        static::saving(function ($plainte) {
            if ($plainte->date_echeance?->isPast()
                && ! in_array($plainte->statut, ['resolue', 'rejetee', 'annulee'])
            ) {
                $plainte->statut = 'en_retard';
            }
        });
    }

    // ── Relations ─────────────────────────────────────────────

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function zone()
    {
        return $this->belongsTo(ZoneInfluence::class, 'zone_id');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeParStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    // ── Accesseurs ────────────────────────────────────────────

    public function getLabelStatutAttribute(): string
    {
        return match ($this->statut) {
            'recue'            => 'Reçue',
            'en_cours'         => 'En cours',
            'en_investigation' => 'Investigation',
            'resolue'          => 'Résolue',
            'en_retard'        => 'En retard',
            'rejetee'          => 'Rejetée',
            'escaladee'        => 'Escaladée',
            default            => 'Inconnu',
        };
    }

    public function getCouleurStatutAttribute(): string
    {
        return match ($this->statut) {
            'recue'            => 'blue',
            'en_cours'         => 'orange',
            'en_investigation' => 'purple',
            'resolue'          => 'green',
            'en_retard'        => 'red',
            'rejetee'          => 'gray',
            'escaladee'        => 'darkred',
            default            => 'gray',
        };
    }
}
