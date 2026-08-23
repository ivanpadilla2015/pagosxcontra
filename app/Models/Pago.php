<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Pago extends Model
{
    protected $fillable = [
        'numero',
        'fecha',
        'contrato_id',
        'cansecu_pagos',
        'cansecu_infor',
        'cansecu_tramite',
        'informe_id',
        'tramite_pago_id',
        'valor_total',
        'estado',
        'fecha_cierre',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_cierre' => 'date',
        'valor_total' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function informe(): BelongsTo
    {
        return $this->belongsTo(Informe::class);
    }

    public function tramitePago(): BelongsTo
    {
        return $this->belongsTo(TramitePago::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePago::class);
    }

    public function facturas(): HasManyThrough
    {
        return $this->hasManyThrough(Factura::class, DetallePago::class);
    }

    public function registrosSnapshot(): HasMany
    {
        return $this->hasMany(Pagodetaregistro::class);
    }

    public function rubrosSnapshot(): HasMany
    {
        return $this->hasMany(Pagodeterubro::class);
    }
}
