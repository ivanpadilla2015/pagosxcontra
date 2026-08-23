<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Obligacion extends Model
{
    use HasFactory;

    protected $fillable = [
        'numeral',
        'obligacion_deta',
        'entregable',
        'contrato_id',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
