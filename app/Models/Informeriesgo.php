<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Informeriesgo extends Model
{
    protected $fillable = [
        'tipo',
        'descripcion',
        'tratamiento',
        'responsable',
        'periodicidad',
        'informe_id',
    ];

    public function informe(): BelongsTo
    {
        return $this->belongsTo(Informe::class);
    }
}
