<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubro extends Model
{
    protected $fillable = [
        'codigo_rubro',
        'nombre_rubro',
    ];

    /**
     * Usos asociados a este rubro.
     */
    public function usos(): HasMany
    {
        return $this->hasMany(Uso::class);
    }
}
