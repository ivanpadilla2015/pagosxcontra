<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetencionesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    private ?string $fechaInicio;
    private ?string $fechaFin;
    private ?int $proveedorId;
    private ?int $contratoId;
    private string $tab;
    private int $fila = 0;

    public function __construct(?string $fechaInicio, ?string $fechaFin, ?int $proveedorId, ?int $contratoId, string $tab)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->proveedorId = $proveedorId;
        $this->contratoId = $contratoId;
        $this->tab = $tab;
    }

    public function collection()
    {
        $q = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id')
            ->where('facturas.estado', '!=', 'anulada');

        if ($this->fechaInicio) {
            $q->where('facturas.fecha', '>=', $this->fechaInicio);
        }
        if ($this->fechaFin) {
            $q->where('facturas.fecha', '<=', $this->fechaFin);
        }
        if ($this->proveedorId) {
            $q->where('facturas.proveedor_id', $this->proveedorId);
        }
        if ($this->contratoId) {
            $q->where('facturas.contrato_id', $this->contratoId);
        }

        if ($this->tab === 'contrato') {
            return $this->agruparPor($q, 'facturas.contrato_id', 'contrato');
        } elseif ($this->tab === 'proveedor') {
            return $this->agruparPor($q, 'facturas.proveedor_id', 'proveedor');
        } else {
            return $this->agruparPorFactura($q);
        }
    }

    private function agruparPor($q, string $groupBy, string $tipo): \Illuminate\Support\Collection
    {
        $baseQuery = clone $q;

        $invoiceTotals = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->where('facturas.estado', '!=', 'anulada')
            ->select(
                $groupBy . ' as grupo_id',
                DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
                DB::raw('SUM(factura_lineas.valor_iva) as iva'),
                DB::raw('SUM(factura_lineas.valor_con_iva) as total')
            )
            ->groupBy($groupBy)
            ->get()
            ->keyBy('grupo_id');

        $result = $q->select(
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
            ->get();

        $model = $tipo === 'contrato' ? \App\Models\Contrato::class : \App\Models\Proveedor::class;

        return $result->map(function ($row) use ($model, $tipo, $invoiceTotals) {
            $obj = $model::find($row->grupo_id);
            $this->fila++;
            $totals = $invoiceTotals->get($row->grupo_id);
            $subtotal = $totals->subtotal ?? 0;
            $iva = $totals->iva ?? 0;
            $total = $totals->total ?? 0;
            return [
                '#' => $this->fila,
                $tipo === 'contrato' ? 'Contrato' : 'Proveedor' => $tipo === 'contrato' ? ($obj->numcontrato ?? '-') : ($obj->nombre ?? '-'),
                'Proveedor' => $tipo === 'contrato' ? ($obj->proveedor->nombre ?? '-') : '-',
                'Facturas' => $row->total_facturas,
                'Subtotal' => $subtotal,
                'IVA' => $iva,
                'Retefuente' => $row->retefuente,
                'Reteiva' => $row->reteiva,
                'Reteica' => $row->reteica,
                'Fedepapa' => $row->fedepapa,
                'Asohofrucol' => $row->asohofrucol,
                'Estampilla' => $row->estampilla,
                'Total Retenciones' => $row->total_retenciones,
                'Total Neto' => $total,
            ];
        });
    }

    private function agruparPorFactura($q): \Illuminate\Support\Collection
    {
        $result = $q->select(
                'facturas.id as factura_id',
                'facturas.numero',
                'facturas.fecha',
                'facturas.estado',
                'facturas.subtotal',
                'facturas.total_iva',
                'facturas.total',
                'facturas.proveedor_id',
                'facturas.contrato_id',
                DB::raw("SUM(CASE WHEN retenciones.name = 'Retefuente' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as retefuente"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteiva' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteiva"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Reteica' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteica"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Fedepapa' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as fedepapa"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Asohofrucol' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as asohofrucol"),
                DB::raw("SUM(CASE WHEN retenciones.name = 'Estampilla Magdalena' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as estampilla"),
                DB::raw('SUM(factura_linea_retenciones.valor_retenido) as total_retenciones')
            )
            ->groupBy('facturas.id', 'facturas.numero', 'facturas.fecha', 'facturas.estado', 'facturas.subtotal', 'facturas.total_iva', 'facturas.total', 'facturas.proveedor_id', 'facturas.contrato_id')
            ->orderByDesc('facturas.fecha')
            ->get();

        $proveedores = \App\Models\Proveedor::whereIn('id', $result->pluck('proveedor_id')->unique())->pluck('nombre', 'id');
        $contratos = \App\Models\Contrato::whereIn('id', $result->pluck('contrato_id')->unique())->pluck('numcontrato', 'id');

        return $result->map(function ($row) use ($proveedores, $contratos) {
            $this->fila++;
            $partes = explode('-', $row->numero ?? '');
            return [
                '#' => $this->fila,
                'Número' => $partes[1] ?? $row->numero,
                'Fecha' => $row->fecha,
                'Proveedor' => $proveedores[$row->proveedor_id] ?? '-',
                'Contrato' => $contratos[$row->contrato_id] ?? '-',
                'Subtotal' => $row->subtotal,
                'IVA' => $row->total_iva,
                'Retefuente' => $row->retefuente,
                'Reteiva' => $row->reteiva,
                'Reteica' => $row->reteica,
                'Fedepapa' => $row->fedepapa,
                'Asohofrucol' => $row->asohofrucol,
                'Estampilla' => $row->estampilla,
                'Total Retenciones' => $row->total_retenciones,
                'Total' => $row->total,
            ];
        });
    }

    public function headings(): array
    {
        if ($this->tab === 'contrato') {
            return ['#', 'Contrato', 'Proveedor', 'Facturas', 'Subtotal', 'IVA', 'Retefuente', 'Reteiva', 'Reteica', 'Fedepapa', 'Asohofrucol', 'Estampilla', 'Total Retenciones', 'Total Neto'];
        } elseif ($this->tab === 'proveedor') {
            return ['#', 'Proveedor', 'Facturas', 'Subtotal', 'IVA', 'Retefuente', 'Reteiva', 'Reteica', 'Fedepapa', 'Asohofrucol', 'Estampilla', 'Total Retenciones', 'Total Neto'];
        }
        return ['#', 'Número', 'Fecha', 'Proveedor', 'Contrato', 'Subtotal', 'IVA', 'Retefuente', 'Reteiva', 'Reteica', 'Fedepapa', 'Asohofrucol', 'Estampilla', 'Total Retenciones', 'Total'];
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
