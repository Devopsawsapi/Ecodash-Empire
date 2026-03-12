<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * Champs modifiables (Mass Assignment)
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'matricule',
        'role',
        'modules_acces',
        'zone_affectation',
        'photo',
        'actif',
    ];

    /**
     * Champs cachés dans les réponses JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast des types
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'modules_acces' => 'array',
        'actif' => 'boolean',
    ];
}