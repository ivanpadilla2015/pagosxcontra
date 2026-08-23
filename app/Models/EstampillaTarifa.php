<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstampillaTarifa extends Model
{
    protected $fillable = [
        'retencion_id',
        'departamento',
        'tipo_adquisicion',
        'porcentaje',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
    ];

    public function retencion(): BelongsTo
    {
        return $this->belongsTo(Retencion::class);
    }
}
