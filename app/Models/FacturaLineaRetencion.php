<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaLineaRetencion extends Model
{
    protected $table = 'factura_linea_retenciones';

    protected $fillable = [
        'factura_linea_id',
        'retencion_id',
        'base_calculo',
        'porcentaje_aplicado',
        'valor_retenido',
    ];

    protected $casts = [
        'porcentaje_aplicado' => 'decimal:2',
        'valor_retenido' => 'decimal:2',
    ];

    public function facturaLinea(): BelongsTo
    {
        return $this->belongsTo(FacturaLinea::class);
    }

    public function retencion(): BelongsTo
    {
        return $this->belongsTo(Retencion::class);
    }
}
