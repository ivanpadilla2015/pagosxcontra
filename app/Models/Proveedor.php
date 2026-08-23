<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Proveedor extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'nit',
        'digver',
        'email',
        'telefono',
        'direccion',
        'representante_legal',
        'tipoper_id',
        'regimen_tributario_id',
        'tiene_excepcion_retenciones',
        'es_declarante',
        'codigo_actividad_economica',
        'descripcion_actividad',
        'name_cuenta_bancaria',
        'numero_cuenta',
        'tipocuenta_id',
    ];

    protected $casts = [
        'tiene_excepcion_retenciones' => 'boolean',
        'es_declarante' => 'boolean',
    ];

    public function tipoper(): BelongsTo
    {
        return $this->belongsTo(Tipoper::class);
    }

    public function regimenTributario(): BelongsTo
    {
        return $this->belongsTo(RegimenTributario::class);
    }

    public function tipocuenta(): BelongsTo
    {
        return $this->belongsTo(Tipocuenta::class);
    }

    /**
     * Retenciones marcadas manualmente como excepción para este proveedor
     * (pivote proveedor_retencion). Solo se usan si tiene_excepcion_retenciones = true.
     */
    public function retencionesExcepcion(): BelongsToMany
    {
        return $this->belongsToMany(Retencion::class, 'proveedor_retencion');
    }

    /**
     * Retenciones que realmente aplican al proveedor (Derivación A).
     *
     * Regla de resolución:
     * - Si tiene_excepcion_retenciones = true -> usa las retenciones manuales
     *   guardadas en el pivote proveedor_retencion.
     * - Si es false (caso normal) -> deriva automáticamente las retenciones
     *   del régimen tributario asignado. No se persiste nada en el proveedor.
     *
     * Uso: $proveedor->retencionesAplicables
     *
     * @return \Illuminate\Support\Collection<int, Retencion>
     */
    public function getRetencionesAplicablesAttribute(): Collection
    {
        if ($this->tiene_excepcion_retenciones) {
            return $this->retencionesExcepcion()->get();
        }

        return $this->regimenTributario
            ? $this->regimenTributario->retenciones()->get()
            : collect();
    }

    /**
     * Tarifas de Reteica servicio para este proveedor.
     */
    public function reteicaTarifas()
    {
        return $this->hasMany(ReteicaTarifa::class);
    }

    /**
     * Contratos de este proveedor.
     */
    public function contratos()
    {
        return $this->hasMany(Contrato::class);
    }
}
