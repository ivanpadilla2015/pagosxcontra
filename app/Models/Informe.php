<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Informe extends Model
{
    protected $fillable = [
        'cansecu_infor',
        'fecha',
        'contrato_id',
        'tramite_pago_id',
        'estado',
        'total_info',
        'saldo_viene',
        'porcentaje_cumplimiento',
        'mes_ejecucion',
        'corresponde_texto_periodo',
        'novedad',
        'fiducia',
        'infopersonal',
        'infoaiu',
        'anexos',
        'recomendacion',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_info' => 'decimal:2',
        'saldo_viene' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function tramitePago(): BelongsTo
    {
        return $this->belongsTo(TramitePago::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function informeobligaciones(): HasMany
    {
        return $this->hasMany(Informeobligacion::class);
    }

    public function informeriesgos(): HasMany
    {
        return $this->hasMany(Informeriesgo::class);
    }

    public function informeregistros(): HasMany
    {
        return $this->hasMany(InformeRegistro::class);
    }
}
