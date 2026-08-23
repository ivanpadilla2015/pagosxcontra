<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagodetaregistro extends Model
{
    protected $table = 'pagodetaregistros';

    protected $fillable = [
        'pago_id',
        'registro_id',
        'numero_reg',
        'valor_reg',
        'fecha_reg',
        'estado',
        'newplazoejecucion',
        'tiporegistro_id',
    ];

    protected $casts = [
        'fecha_reg' => 'date',
        'newplazoejecucion' => 'date',
        'valor_reg' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }

    public function tiporegistro(): BelongsTo
    {
        return $this->belongsTo(Tiporegistro::class);
    }
}
