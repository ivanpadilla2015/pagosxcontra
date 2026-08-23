<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportePagosRetencionesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    private ?string $fechaInicio;
    private ?string $fechaFin;
    private ?int $contratoId;
    private int $fila = 0;

    public function __construct(?string $fechaInicio, ?string $fechaFin, ?int $contratoId)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->contratoId = $contratoId;
    }

    public function collection()
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

        $q = DB::table('pagos')
            ->join('detalle_pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->join('facturas', 'facturas.id', '=', 'detalle_pagos.factura_id')
            ->join('proveedors', 'proveedors.id', '=', 'facturas.proveedor_id')
            ->join('movirubros', 'movirubros.id', '=', 'detalle_pagos.movirubro_id')
            ->join('registros', 'registros.id', '=', 'movirubros.registro_id')
            ->leftJoinSub($retencionesSubquery, 'ret_sub', 'ret_sub.factura_id', '=', 'facturas.id')
            ->where('pagos.estado', 'cerrado');

        if ($this->contratoId) {
            $q->where('pagos.contrato_id', $this->contratoId);
        }
        if ($this->fechaInicio) {
            $q->where('pagos.fecha', '>=', $this->fechaInicio);
        }
        if ($this->fechaFin) {
            $q->where('pagos.fecha', '<=', $this->fechaFin);
        }

        $result = $q->select(
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

        $facturaIds = $result->pluck('factura_id')->unique()->toArray();

        $invoiceTotals = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->whereIn('facturas.id', $facturaIds)
            ->select(
                'facturas.id as factura_id',
                DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
                DB::raw('SUM(factura_lineas.valor_iva) as iva'),
                DB::raw('SUM(factura_lineas.valor_con_iva) as total')
            )
            ->groupBy('facturas.id')
            ->get()
            ->keyBy('factura_id');

        $proveedores = \App\Models\Proveedor::whereIn('id', $result->pluck('proveedor_id')->unique())->pluck('nombre', 'id');

        return $result->map(function ($row) use ($invoiceTotals, $proveedores) {
            $this->fila++;
            $totals = $invoiceTotals->get($row->factura_id);
            $subtotal = $totals->subtotal ?? 0;
            $iva = $totals->iva ?? 0;
            $partes = explode('-', $row->factura_numero ?? '');
            return [
                '#' => $this->fila,
                'N° Pago' => $row->pago_numero,
                'Fecha Pago' => $row->pago_fecha,
                'N° Factura' => $partes[1] ?? $row->factura_numero,
                'Proveedor' => $proveedores[$row->proveedor_id] ?? '-',
                'NIT' => $row->proveedor_nit ?? '-',
                'N° Registro' => $row->numero_reg ?? '-',
                'Fecha Factura' => $row->factura_fecha,
                'Subtotal' => $subtotal,
                'IVA' => $iva,
                'Total Sin Retenciones' => $subtotal + $iva,
                'Retefuente' => $row->retefuente,
                'Reteiva' => $row->reteiva,
                'Reteica' => $row->reteica,
                'Fedepapa' => $row->fedepapa,
                'Asohofrucol' => $row->asohofrucol,
                'Estampilla' => $row->estampilla,
                'Total Retenciones' => $row->total_retenciones,
                'Total Neto' => $subtotal + $iva - $row->total_retenciones,
            ];
        });
    }

    public function headings(): array
    {
        return ['#', 'N° Pago', 'Fecha Pago', 'N° Factura', 'Proveedor', 'NIT', 'N° Registro', 'Fecha Factura', 'Subtotal', 'IVA', 'Total Sin Retenciones', 'Retefuente', 'Reteiva', 'Reteica', 'Fedepapa', 'Asohofrucol', 'Estampilla', 'Total Retenciones', 'Total Neto'];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
