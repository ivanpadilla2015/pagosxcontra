<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movirubro extends Model
{
     protected $fillable = [
        'valor_rubro',
        'saldo_rubro',
        'dependencia_afectacion',
        'registro_id',
        'rubro_id',
        'contrato_id',
        'movirubro_padre_id',
    ];

    protected function casts(): array
    {
        return [
            'valor_rubro' => 'decimal:2',
            'saldo_rubro' => 'decimal:2',
        ];
    }

    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(rubro::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function movirubroPadre(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class, 'movirubro_padre_id');
    }

    public function movirubrosHijos()
    {
        return $this->hasMany(Movirubro::class, 'movirubro_padre_id');
    }
}
