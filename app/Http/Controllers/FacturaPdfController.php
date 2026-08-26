<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Barryvdh\DomPDF\Facade\Pdf;

class FacturaPdfController extends Controller
{
    public function show(int $id)
    {
        $factura = Factura::with([
            'proveedor.regimenTributario',
            'contrato',
            'municipio',
            'dependencia',
            'lineas.itemcontrato.producto',
            'lineas.municipio',
            'lineas.retenciones.retencion',
        ])->findOrFail($id);

        $subtotal = (float) $factura->subtotal;
        $totalIva = (float) $factura->total_iva;
        $totalRetenciones = (float) $factura->total_retenciones;
        $totalSinRet = (float) $subtotal + (float) $totalIva;
        $totalNeto = (float) $totalSinRet - (float) $totalRetenciones;

        $retencionesDetalle = $factura->lineas->flatMap(function ($linea) {
            $municipio = $linea->municipio->nombre ?? '-';
            $tipoAdquisicion = $linea->tipo_adquisicion ?? '-';
            return $linea->retenciones->map(function ($ret) use ($linea, $municipio, $tipoAdquisicion) {
                $baseNumerica = (float) ($ret->retencion->aplica_iva ? $linea->valor_iva : $linea->valor_base);
                return [
                    'nombre' => $ret->retencion->name ?? '-',
                    'tipo' => $ret->retencion->tipo ?? '-',
                    'base' => $baseNumerica,
                    'porcentaje' => (float) $ret->porcentaje_aplicado,
                    'valor' => (float) $ret->valor_retenido,
                    'municipio' => $municipio,
                    'tipo_adquisicion' => $tipoAdquisicion,
                    'clave' => ($ret->retencion->name ?? '-') . '_' . number_format((float) $ret->porcentaje_aplicado, 1) . '_' . $municipio,
                ];
            });
        })->values();

        $retencionesAgrupadas = $retencionesDetalle->groupBy('clave')->map(function ($items) {
            return [
                'nombre' => $items->first()['nombre'],
                'tipo' => $items->first()['tipo'],
                'base' => (float) $items->sum('base'),
                'porcentaje' => (float) $items->first()['porcentaje'],
                'valor' => (float) $items->sum('valor'),
                'municipio' => $items->first()['municipio'],
                'tipo_adquisicion' => $items->first()['tipo_adquisicion'],
            ];
        })->values();

        $retencionesPorcentaje = $retencionesDetalle->groupBy(function ($item) {
            return number_format($item['porcentaje'], 1);
        })->map(function ($items, $pctLabel) {
            return [
                'porcentaje_label' => $pctLabel . '%',
                'nombres' => $items->pluck('nombre')->unique()->implode(', '),
                'valor' => (float) $items->sum('valor'),
            ];
        })->sortByDesc('valor')->values();

        $lineasPdf = $factura->lineas->map(function ($linea) {
            return [
                'producto_nombre' => $linea->itemcontrato->producto->name ?? '-',
                'tipo_adquisicion' => $linea->tipo_adquisicion,
                'cantidad' => (float) $linea->cantidad,
                'valor_costo_unit' => (float) ($linea->itemcontrato->valor_costo ?? 0),
                'iva_unit' => $linea->itemcontrato->iva ?? 0,
                'valor_base' => (float) $linea->valor_base,
                'valor_iva' => (float) $linea->valor_iva,
                'valor_con_iva' => (float) $linea->valor_con_iva,
                'retenciones' => $linea->retenciones->map(function ($ret) {
                    return [
                        'nombre' => $ret->retencion->name ?? '-',
                        'porcentaje' => (float) $ret->porcentaje_aplicado,
                        'valor' => (float) $ret->valor_retenido,
                    ];
                }),
            ];
        });

        $logoPath = public_path('images/Logo.png');

        $pdf = Pdf::loadView('reportes.factura-pdf', [
            'factura' => $factura,
            'logoPath' => $logoPath,
            'subtotal' => $subtotal,
            'totalIva' => $totalIva,
            'totalRetenciones' => $totalRetenciones,
            'totalSinRet' => $totalSinRet,
            'totalNeto' => $totalNeto,
            'retencionesAgrupadas' => $retencionesAgrupadas,
            'retencionesDetalle' => $retencionesDetalle,
            'retencionesPorcentaje' => $retencionesPorcentaje,
            'lineasPdf' => $lineasPdf,
        ])->setPaper('letter');

        $numeroLimpio = explode('-', $factura->numero)[1] ?? $factura->numero;
        return $pdf->download('factura_' . $numeroLimpio . '_' . now()->format('Ymd_His') . '.pdf');
    }
}
