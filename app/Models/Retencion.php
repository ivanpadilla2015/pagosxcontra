<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Retencion extends Model
{
    use HasFactory;

    protected $table = 'retenciones';

    protected $fillable = [
        'name',
        'tipo',
        'aplica_base',
        'aplica_iva',
        'divisor',
    ];

    protected $casts = [
        'aplica_base' => 'boolean',
        'aplica_iva' => 'boolean',
        'divisor' => 'integer',
    ];

    /**
     * Regímenes tributarios que aplican esta retención (pivote regimen_retencion).
     */
    public function regimenes(): BelongsToMany
    {
        return $this->belongsToMany(RegimenTributario::class, 'regimen_retencion');
    }

    /**
     * Proveedores que tienen esta retención como excepción (pivote proveedor_retencion).
     */
    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(Proveedor::class, 'proveedor_retencion');
    }

    /**
     * Productos que disparan esta retención parafiscal (pivote producto_retencion).
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_retencion');
    }

    /**
     * Tarifas parametrizables de esta retención.
     */
    public function tarifas()
    {
        return $this->hasMany(RetencionTarifa::class);
    }

    /**
     * Tarifas de estampilla territorial.
     */
    public function estampillaTarifas()
    {
        return $this->hasMany(EstampillaTarifa::class);
    }
}
