<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePago extends Model
{
    protected $fillable = [
        'pago_id',
        'factura_id',
        'movirubro_id',
        'uso_id',
        'rubro_id',
        'valor_pagado',
    ];

    protected $casts = [
        'valor_pagado' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function movirubro(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class);
    }

    public function uso(): BelongsTo
    {
        return $this->belongsTo(Uso::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }
}
