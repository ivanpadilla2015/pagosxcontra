<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $fillable = [
        'proveedor_id',
        'contrato_id',
        'numero',
        'numero_migo',
        'fecha_migo',
        'fecha',
        'estado',
        'municipio_id',
        'dependencia_id',
        'subtotal',
        'total_iva',
        'total_retenciones',
        'total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_migo' => 'date',
        'subtotal' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'total_retenciones' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class)->orderBy('id');
    }

    /**
     * Genera el siguiente número de factura para el proveedor.
     */
    public static function siguienteNumero(int $proveedorId, int $year): string
    {
        $ultimoNumero = self::where('proveedor_id', $proveedorId)
            ->whereYear('fecha', $year)
            ->max('numero');

        if ($ultimoNumero) {
            $num = (int) explode('-', $ultimoNumero)[0];
            $num++;
        } else {
            $num = 1;
        }

        return str_pad($num, 3, '0', STR_PAD_LEFT) . '-' . $year;
    }
}
