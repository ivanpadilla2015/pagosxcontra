<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReteicaTarifa extends Model
{
    protected $fillable = [
        'proveedor_id',
        'municipio_id',
        'tipo_adquisicion',
        'porcentaje',
        'codigo_actividad',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
