<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acta extends Model
{
    protected $fillable = [
        'numero',
        'factura_id',
        'contrato_id',
        'dependencia_id',
        'nombre_entrega',
        'cargo_entrega',
        'en_calidad_de',
        'fecha',
        'hora',
        'inspeccion_visual',
        'informes_laboratorio',
        'certificacion_expedida',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
