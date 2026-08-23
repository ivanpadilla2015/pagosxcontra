<?php

namespace App\Http\Controllers;

use App\Exports\RetencionesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteRetencionesController extends Controller
{
    public function excel(Request $request)
    {
        $tab = $request->query('tab', 'contrato');
        $fileName = 'retenciones_' . $tab . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new RetencionesExport(
                $request->query('fecha_inicio'),
                $request->query('fecha_fin'),
                $request->query('proveedor_id') ? (int) $request->query('proveedor_id') : null,
                $request->query('contrato_id') ? (int) $request->query('contrato_id') : null,
                $tab
            ),
            $fileName
        );
    }

    public function pdf(Request $request)
    {
        $tab = $request->query('tab', 'contrato');
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $proveedorId = $request->query('proveedor_id') ? (int) $request->query('proveedor_id') : null;
        $contratoId = $request->query('contrato_id') ? (int) $request->query('contrato_id') : null;

        $data = $this->getData($tab, $fechaInicio, $fechaFin, $proveedorId, $contratoId);

        $pdf = Pdf::loadView('reportes.retenciones-pdf', [
            'tab' => $tab,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'datos' => $data['datos'],
            'resumen' => $data['resumen'],
        ])->setPaper('landscape');

        return $pdf->download('retenciones_' . $tab . '_' . now()->format('Y-m-d_His') . '.pdf');
    }

    private function getData(string $tab, ?string $fechaInicio, ?string $fechaFin, ?int $proveedorId, ?int $contratoId): array
    {
        $base = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id')
            ->where('facturas.estado', '!=', 'anulada');

        if ($fechaInicio) $base->where('facturas.fecha', '>=', $fechaInicio);
        if ($fechaFin) $base->where('facturas.fecha', '<=', $fechaFin);
        if ($proveedorId) $base->where('facturas.proveedor_id', $proveedorId);
        if ($contratoId) $base->where('facturas.contrato_id', $contratoId);

        $invoiceQuery = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->where('facturas.estado', '!=', 'anulada');

        if ($fechaInicio) $invoiceQuery->where('facturas.fecha', '>=', $fechaInicio);
        if ($fechaFin) $invoiceQuery->where('facturas.fecha', '<=', $fechaFin);
        if ($proveedorId) $invoiceQuery->where('facturas.proveedor_id', $proveedorId);
        if ($contratoId) $invoiceQuery->where('facturas.contrato_id', $contratoId);

        $invoiceTotals = (clone $invoiceQuery)->select(
            DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
            DB::raw('SUM(factura_lineas.valor_iva) as iva'),
            DB::raw('SUM(factura_lineas.valor_con_iva) as total')
        )->first();

        $retentionTotals = (clone $base)->select(
            DB::raw('COUNT(DISTINCT facturas.id) as total_facturas'),
            DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'general' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_general"),
            DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'parafiscal' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_parafiscal"),
            DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'territorial' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_territorial"),
            DB::raw('COALESCE(SUM(factura_linea_retenciones.valor_retenido), 0) as sum_retenciones')
        )->first();

        $retentionTotals->sum_subtotal = $invoiceTotals->subtotal ?? 0;
        $retentionTotals->sum_iva = $invoiceTotals->iva ?? 0;
        $retentionTotals->sum_total = $invoiceTotals->total ?? 0;

        $resumen = $retentionTotals;

        if ($tab === 'contrato') {
            $groupBy = 'facturas.contrato_id';
        } elseif ($tab === 'proveedor') {
            $groupBy = 'facturas.proveedor_id';
        } else {
            $groupBy = 'facturas.id';
        }

        $datos = $base->select(
                $groupBy . ' as grupo_id',
                DB::raw("SUM(CASE WHEN retenciones.name = 'Retefuente' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as retefuente"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteiva' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteiva"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteica' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteica"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Fedepapa' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as fedepapa"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Asohofrucol' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as asohofrucol"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Estampilla Magdalena' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as estampilla"),
                DB::raw('SUM(factura_linea_retenciones.valor_retenido) as total_retenciones'),
                DB::raw('COUNT(DISTINCT facturas.id) as total_facturas')
            )
            ->groupBy($groupBy)
            ->orderByDesc('total_retenciones')
            ->get()
            ->map(function ($row) use ($tab) {
                $row = (array) $row;
                if ($tab === 'contrato') {
                    $obj = \App\Models\Contrato::with('proveedor')->find($row['grupo_id']);
                    $row['nombre'] = $obj->numcontrato ?? '-';
                    $row['proveedor_nombre'] = $obj->proveedor->nombre ?? '-';
                } elseif ($tab === 'proveedor') {
                    $obj = \App\Models\Proveedor::find($row['grupo_id']);
                    $row['nombre'] = $obj->nombre ?? '-';
                } else {
                    $factura = \App\Models\Factura::find($row['grupo_id']);
                    $partes = explode('-', $factura->numero ?? '');
                    $row['numero'] = $partes[1] ?? $factura->numero ?? '-';
                    $row['fecha'] = $factura->fecha ?? '-';
                    $row['nombre'] = $factura->proveedor->nombre ?? '-';
                    $row['contrato_num'] = $factura->contrato->numcontrato ?? '-';
                }
                return $row;
            });

        return ['datos' => $datos, 'resumen' => $resumen];
    }
}
