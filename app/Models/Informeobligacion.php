<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Informeobligacion extends Model
{
    protected $fillable = [
        'numeral',
        'obligacion_deta',
        'entregable',
        'confirmar',
        'informe_id',
        'contrato_id',
    ];

    public function informe(): BelongsTo
    {
        return $this->belongsTo(Informe::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
