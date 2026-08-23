<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramitePagoDocumento extends Model
{
    protected $fillable = [
        'tramite_pago_id',
        'tipo',
        'nombre_documento',
        'fecha',
        'valor',
        'folio',
        'reposa_expediente',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'decimal:2',
            'folio' => 'integer',
            'reposa_expediente' => 'boolean',
        ];
    }

    public function tramitePago(): BelongsTo
    {
        return $this->belongsTo(TramitePago::class);
    }
}
