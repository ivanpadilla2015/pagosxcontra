<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traslado extends Model
{
    protected $fillable = [
        'contrato_id',
        'movirubro_origen_id',
        'movirubro_destino_id',
        'valor',
        'estado',
        'user_propone_id',
        'user_aprueba_id',
        'fecha_aprobacion',
        'observaciones',
        'registro_id',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha_aprobacion' => 'datetime',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function movirubroOrigen(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class, 'movirubro_origen_id');
    }

    public function movirubroDestino(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class, 'movirubro_destino_id');
    }

    public function userPropone(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_propone_id');
    }

    public function userAprueba(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_aprueba_id');
    }

    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }
}
