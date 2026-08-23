<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dependencia extends Model
{
    protected $fillable = [
        'name',
        'direccion',
        'municipio_id',
        'regional_id',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class);
    }
}
