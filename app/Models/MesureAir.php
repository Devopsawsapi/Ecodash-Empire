<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesureAir extends Model
{
    protected $table = 'mesures_air';

    protected $fillable = [
        'site_id', 'technicien_id', 'pm25', 'pm10', 'pm1', 'tsp', 'co2', 'co', 'no2', 'so2',
        'o3', 'nh3', 'h2s', 'voc', 'niveau_bruit_db', 'periode_bruit', 'temperature_air',
        'humidite_relative', 'pression_atm', 'vitesse_vent', 'direction_vent', 'iqa',
        'categorie_iqa', 'statut_global', 'polluants_dominants', 'observations', 'photo_site',
        'methode_mesure', 'date_mesure', 'valide', 'validee_par',
    ];

    protected $casts = [
        'date_mesure'         => 'datetime',
        'polluants_dominants' => 'array',
        'valide'              => 'boolean',
    ];

    // Seuils OMS (µg/m³ annuels 2021)
    const SEUILS = [
        'pm25'            => ['max' => 15,  'critical' => false],
        'pm10'            => ['max' => 45,  'critical' => false],
        'co'              => ['max' => 4,   'critical' => true],
        'no2'             => ['max' => 25,  'critical' => false],
        'so2'             => ['max' => 40,  'critical' => true],
        'o3'              => ['max' => 100, 'critical' => false],
        'niveau_bruit_db' => ['max' => 55,  'critical' => false],
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($mesure) {
            $mesure->calculerIQA();
        });
    }

    protected function calculerIQA(): void
    {
        $iqa = 50; // base

        if ($this->pm25) {
            if ($this->pm25 > 55)     $iqa = max($iqa, 200);
            elseif ($this->pm25 > 35) $iqa = max($iqa, 150);
            elseif ($this->pm25 > 15) $iqa = max($iqa, 100);
            else                      $iqa = max($iqa, 50);
        }

        if ($this->no2 && $this->no2 > 200) $iqa = max($iqa, 200);
        if ($this->so2 && $this->so2 > 200) $iqa = max($iqa, 250);
        if ($this->co  && $this->co  > 10)  $iqa = max($iqa, 200);

        $this->iqa = $iqa;

        $this->categorie_iqa = match (true) {
            $iqa <= 50  => 'bon',
            $iqa <= 100 => 'modere',
            $iqa <= 150 => 'mauvais_groupes_sensibles',
            $iqa <= 200 => 'mauvais',
            $iqa <= 300 => 'tres_mauvais',
            default     => 'dangereux',
        };

        $this->statut_global = match (true) {
            $iqa <= 50  => 'bon',
            $iqa <= 100 => 'modere',
            $iqa <= 200 => 'mauvais',
            default     => 'critique',
        };

        // Polluants dominants
        $dominants = [];
        if ($this->pm25 && $this->pm25 > 15) $dominants[] = 'PM2.5';
        if ($this->pm10 && $this->pm10 > 45) $dominants[] = 'PM10';
        if ($this->no2  && $this->no2  > 25) $dominants[] = 'NO2';
        if ($this->so2  && $this->so2  > 40) $dominants[] = 'SO2';

        $this->polluants_dominants = $dominants;
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
