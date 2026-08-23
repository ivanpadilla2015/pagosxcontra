<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Riesgo extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo',
        'descripcion',
        'tratamiento',
        'responsable',
        'periodicidad',
        'contrato_id',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
