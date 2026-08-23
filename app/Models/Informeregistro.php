<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformeRegistro extends Model
{
    protected $table = 'informeregistros';

    protected $fillable = [
        'numero_reg',
        'valor_reg',
        'fecha_reg',
        'newplazoejecucion',
        'tiporegistro_id',
        'informe_id',
    ];

    protected $casts = [
        'fecha_reg' => 'date',
        'newplazoejecucion' => 'date',
        'valor_reg' => 'decimal:2',
    ];

    public function informe(): BelongsTo
    {
        return $this->belongsTo(Informe::class);
    }

    public function tiporegistro(): BelongsTo
    {
        return $this->belongsTo(Tiporegistro::class);
    }
}
