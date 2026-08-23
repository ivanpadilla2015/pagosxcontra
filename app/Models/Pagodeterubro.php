<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagodeterubro extends Model
{
    protected $table = 'pagodeterubros';

    protected $fillable = [
        'pago_id',
        'movirubro_id',
        'registro_id',
        'rubro_id',
        'valor_rubro',
        'saldo_rubro',
        'dependencia_afectacion',
    ];

    protected $casts = [
        'valor_rubro' => 'decimal:2',
        'saldo_rubro' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function movirubro(): BelongsTo
    {
        return $this->belongsTo(Movirubro::class);
    }

    public function registro(): BelongsTo
    {
        return $this->belongsTo(Registro::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }
}
