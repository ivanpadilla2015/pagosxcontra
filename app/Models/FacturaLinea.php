<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaLinea extends Model
{
    protected $fillable = [
        'factura_id',
        'itemcontrato_id',
        'producto_id',
        'tipo_adquisicion',
        'municipio_id',
        'valor_base',
        'valor_iva',
        'valor_con_iva',
        'cantidad',
        'estampilla_retencion_id',
    ];

    protected $casts = [
        'valor_base' => 'decimal:2',
        'valor_iva' => 'decimal:2',
        'valor_con_iva' => 'decimal:2',
        'cantidad' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function itemcontrato(): BelongsTo
    {
        return $this->belongsTo(Itemcontrato::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function estampillaRetencion(): BelongsTo
    {
        return $this->belongsTo(Retencion::class, 'estampilla_retencion_id');
    }

    public function retenciones(): HasMany
    {
        return $this->hasMany(FacturaLineaRetencion::class);
    }
}
