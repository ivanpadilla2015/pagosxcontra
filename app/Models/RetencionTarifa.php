<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetencionTarifa extends Model
{
    protected $fillable = [
        'retencion_id',
        'es_declarante',
        'tipo_adquisicion',
        'es_agricola',
        'porcentaje',
    ];

    protected $casts = [
        'es_declarante' => 'boolean',
        'es_agricola' => 'boolean',
        'porcentaje' => 'decimal:2',
    ];

    public function retencion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Retencion::class);
    }
}
