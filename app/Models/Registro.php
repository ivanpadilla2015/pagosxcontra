<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registro extends Model
{
     protected $fillable = [
        'numero_reg',
        'fecha_reg',
        'newplazoejecucion',
        'valor_reg',
        'estado',
        'tiporegistro_id',
        'contrato_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_reg' => 'date',
            'newplazoejecucion' => 'date',
            'valor_reg' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function tiporegistro(): BelongsTo
    {
        return $this->belongsTo(Tiporegistro::class);
    }

    public function movirubros(): HasMany
    {
        return $this->hasMany(Movirubro::class);
    }
}
