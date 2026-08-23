<?php

namespace App\Http\Controllers;

use App\Exports\ReportePagosRetencionesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportePagosRetencionesController extends Controller
{
    public function excel(Request $request)
    {
        $fileName = 'pagos_retenciones_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new ReportePagosRetencionesExport(
                $request->query('fecha_inicio'),
                $request->query('fecha_fin'),
                $request->query('contrato_id') ? (int) $request->query('contrato_id') : null
            ),
            $fileName
        );
    }

    public function pdf(Request $request)
    {
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $contratoId = $request->query('contrato_id') ? (int) $request->query('contrato_id') : null;

        $data = $this->getData($fechaInicio, $fechaFin, $contratoId);

        $contrato = $contratoId ? \App\Models\Contrato::with('proveedor')->find($contratoId) : null;

        $pdf = Pdf::loadView('reportes.pagos-retenciones-pdf', [
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'contrato' => $contrato,
            'datos' => $data['datos'],
            'resumen' => $data['resumen'],
        ])->setPaper('landscape');

        return $pdf->download('pagos_retenciones_' . now()->format('Y-m-d_His') . '.pdf');
    }

    private function getData(?string $fechaInicio, ?string $fechaFin, ?int $contratoId): array
    {
        $retencionesSubquery = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id')
            ->select(
                'factura_lineas.factura_id',
                DB::raw("SUM(CASE WHEN retenciones.name = 'Retefuente' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as retefuente"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteiva' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteiva"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteica' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteica"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Fedepapa' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as fedepapa"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Asohofrucol' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as asohofrucol"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Estampilla Magdalena' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as estampilla"),
                DB::raw("SUM(factura_linea_retenciones.valor_retenido) as total_retenciones")
            )
            ->groupBy('factura_lineas.factura_id');

        $base = DB::table('pagos')
            ->join('detalle_pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->join('facturas', 'facturas.id', '=', 'detalle_pagos.factura_id')
            ->join('proveedors', 'proveedors.id', '=', 'facturas.proveedor_id')
            ->join('movirubros', 'movirubros.id', '=', 'detalle_pagos.movirubro_id')
            ->join('registros', 'registros.id', '=', 'movirubros.registro_id')
            ->leftJoinSub($retencionesSubquery, 'ret_sub', 'ret_sub.factura_id', '=', 'facturas.id')
            ->where('pagos.estado', 'cerrado');

        if ($contratoId) $base->where('pagos.contrato_id', $contratoId);
        if ($fechaInicio) $base->where('pagos.fecha', '>=', $fechaInicio);
        if ($fechaFin) $base->where('pagos.fecha', '<=', $fechaFin);

        $invoiceQuery = DB::table('pagos')
            ->join('detalle_pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.estado', 'cerrado');

        if ($contratoId) $invoiceQuery->where('pagos.contrato_id', $contratoId);
        if ($fechaInicio) $invoiceQuery->where('pagos.fecha', '>=', $fechaInicio);
        if ($fechaFin) $invoiceQuery->where('pagos.fecha', '<=', $fechaFin);

        $facturaIds = $invoiceQuery->distinct()->pluck('detalle_pagos.factura_id')->toArray();

        if (empty($facturaIds)) {
            $resumen = (object) [
                'total_facturas' => 0,
                'sum_subtotal' => 0,
                'sum_iva' => 0,
                'sum_retenciones' => 0,
                'sum_total' => 0,
            ];
            return ['datos' => collect(), 'resumen' => $resumen];
        }

        $invoiceTotalsSummary = DB::table('factura_lineas')
            ->whereIn('factura_id', $facturaIds)
            ->select(
                DB::raw('SUM(valor_base) as subtotal'),
                DB::raw('SUM(valor_iva) as iva'),
                DB::raw('SUM(valor_con_iva) as total')
            )->first();

        $sumRetencionesSummary = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->whereIn('factura_lineas.factura_id', $facturaIds)
            ->sum('factura_linea_retenciones.valor_retenido');

        $resumen = (object) [
            'total_facturas' => count($facturaIds),
            'sum_subtotal' => $invoiceTotalsSummary->subtotal ?? 0,
            'sum_iva' => $invoiceTotalsSummary->iva ?? 0,
            'sum_retenciones' => $sumRetencionesSummary ?? 0,
            'sum_total' => ($invoiceTotalsSummary->total ?? 0) - ($sumRetencionesSummary ?? 0),
        ];

        $retentionData = $base->select(
                'pagos.numero as pago_numero',
                'pagos.fecha as pago_fecha',
                'facturas.id as factura_id',
                'facturas.numero as factura_numero',
                'facturas.fecha as factura_fecha',
                'facturas.proveedor_id',
                'proveedors.nit as proveedor_nit',
                DB::raw('MIN(registros.numero_reg) as numero_reg'),
                DB::raw('COALESCE(MIN(ret_sub.retefuente), 0) as retefuente'),
                DB::raw('COALESCE(MIN(ret_sub.reteiva), 0) as reteiva'),
                DB::raw('COALESCE(MIN(ret_sub.reteica), 0) as reteica'),
                DB::raw('COALESCE(MIN(ret_sub.fedepapa), 0) as fedepapa'),
                DB::raw('COALESCE(MIN(ret_sub.asohofrucol), 0) as asohofrucol'),
                DB::raw('COALESCE(MIN(ret_sub.estampilla), 0) as estampilla'),
                DB::raw('COALESCE(MIN(ret_sub.total_retenciones), 0) as total_retenciones')
            )
            ->groupBy(
                'pagos.numero', 'pagos.fecha',
                'facturas.id', 'facturas.numero', 'facturas.fecha', 'facturas.proveedor_id',
                'proveedors.nit'
            )
            ->orderBy('pagos.fecha', 'desc')
            ->orderBy('facturas.numero', 'asc')
            ->get();

        $facturaIdsFromData = $retentionData->pluck('factura_id')->unique()->toArray();

        $invoiceTotals = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->whereIn('facturas.id', $facturaIdsFromData)
            ->select(
                'facturas.id as factura_id',
                DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
                DB::raw('SUM(factura_lineas.valor_iva) as iva')
            )
            ->groupBy('facturas.id')
            ->get()
            ->keyBy('factura_id');

        $datos = $retentionData->map(function ($row) use ($invoiceTotals) {
            $row = (array) $row;
            $partes = explode('-', $row['factura_numero'] ?? '');
            $row['factura_numero'] = $partes[1] ?? $row['factura_numero'];
            $row['proveedor_nombre'] = \App\Models\Proveedor::find($row['proveedor_id'])->nombre ?? '-';
            $row['proveedor_nit'] = $row['proveedor_nit'] ?? '-';
            $row['numero_reg'] = $row['numero_reg'] ?? '-';
            $totals = $invoiceTotals->get($row['factura_id']);
            $row['subtotal'] = $totals->subtotal ?? 0;
            $row['iva'] = $totals->iva ?? 0;
            $row['total_sin_retenciones'] = ($totals->subtotal ?? 0) + ($totals->iva ?? 0);
            $row['total'] = $row['total_sin_retenciones'] - $row['total_retenciones'];
            return $row;
        });

        return ['datos' => $datos, 'resumen' => $resumen];
    }
}
