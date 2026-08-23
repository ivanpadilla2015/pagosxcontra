<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipio extends Model
{
    protected $fillable = [
        'codigo_dane',
        'nombre',
        'departamento',
        'regional_id',
    ];

    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class);
    }
}
