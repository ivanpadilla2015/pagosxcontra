<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Producto extends Model
{
    protected $fillable = [
        'name',
        'tipo',
        'uso_id',
        'rubro_id',
        'regional_id',
        'municipio_id',
        'es_agricola',
    ];

    protected $casts = [
        'es_agricola' => 'boolean',
    ];

    /**
     * Uso (código de uso) asociado al producto.
     */
    public function uso(): BelongsTo
    {
        return $this->belongsTo(Uso::class);
    }

    /**
     * Rubro al que pertenece el producto.
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * Regional a la que pertenece el producto.
     */
    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class);
    }

    /**
     * Municipio al que pertenece el producto (para servicios).
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /**
     * Retenciones parafiscales que dispara este producto (pivote producto_retencion).
     */
    public function retencionesParafiscales(): BelongsToMany
    {
        return $this->belongsToMany(Retencion::class, 'producto_retencion');
    }

    /**
     * Items de contrato que incluyen este producto.
     */
    public function itemcontratos()
    {
        return $this->hasMany(Itemcontrato::class);
    }
}
