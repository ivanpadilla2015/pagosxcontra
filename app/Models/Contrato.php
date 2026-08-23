<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use HasFactory;

    protected $fillable = [
        'numcontrato',
        'fechacontrato',
        'fecha_inicio_contrato',
        'fecha_fin_contrato',
        'objetocontrato',
        'num_mes',

        'cansecu_pagos',
        'cansecu_infor',
        'cansecu_tramite',
        'cansecu_actas',
        'numero_poliza',
        'valor_poliza_asegurado',
        'fecha_poliza_inicio',
        'fecha_poliza_fin',
        'sape_acreedor',
        'orden_erp_sap',
        'expediente_orfeo',
        'proveedor_id',
        'tipocontrato_id',
        'contrainter_id',
        'user_id',
    ];

    protected $casts = [
        'fechacontrato' => 'date',
        'fecha_inicio_contrato' => 'date',
        'fecha_fin_contrato' => 'date',
        'fecha_poliza_inicio' => 'date',
        'fecha_poliza_fin' => 'date',
        'valor_poliza_asegurado' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function tipocontrato(): BelongsTo
    {
        return $this->belongsTo(Tipocontrato::class);
    }

    public function contrainter(): BelongsTo
    {
        return $this->belongsTo(Contrainter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    public function itemcontratos(): HasMany
    {
        return $this->hasMany(Itemcontrato::class);
    }

    public function movirubros(): HasMany
    {
        return $this->hasMany(Movirubro::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    public function informes(): HasMany
    {
        return $this->hasMany(Informe::class);
    }

    public function obligaciones(): HasMany
    {
        return $this->hasMany(Obligacion::class);
    }

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class);
    }

    public function actas(): HasMany
    {
        return $this->hasMany(Acta::class);
    }

    /**
     * Saldo disponible del contrato (suma de saldo_rubro de movirubros).
     */
    public function getSaldoAttribute(): float
    {
        return (float) $this->movirubros()->sum('saldo_rubro');
    }

    /**
     * Valor total del contrato (suma de valor_rubro de movirubros).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->movirubros()->sum('valor_rubro');
    }
}
