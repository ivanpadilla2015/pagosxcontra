<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Uso extends Model
{
    protected $fillable = [
        'codigo_uso',
        'nombre_uso',
        'rubro_id',
    ];

    /**
     * Rubro al que pertenece este uso.
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }
}
