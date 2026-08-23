<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Itemcontrato extends Model
{
    protected $fillable = [
        'unidad',
        'iva',
        'contrato_id',
        'producto_id',
        'movirubro_id',
        'rubro_id',
        'valor_costo',
        'valor_iva',
        'valor_con_iva',
    ];

    protected function casts(): array
    {
        return [
            'iva' => 'decimal:2',
            'valor_costo' => 'decimal:2',
            'valor_iva' => 'decimal:2',
            'valor_con_iva' => 'decimal:2',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function movirubro(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    public function facturaLineas()
    {
        return $this->hasMany(FacturaLinea::class);
    }
}
