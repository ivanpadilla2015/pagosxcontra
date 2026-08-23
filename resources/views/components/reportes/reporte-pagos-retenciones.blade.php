<?php

use App\Models\Contrato;
use App\Traits\FiltrablePorRegional;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    use FiltrablePorRegional;
    #[Url]
    public ?string $fecha_inicio = null;

    #[Url]
    public ?string $fecha_fin = null;

    #[Url]
    public ?int $contrato_id = null;

    #[Computed]
    public function contratos()
    {
        $q = Contrato::with('proveedor')->orderBy('numcontrato');

        if (!auth()->user()->hasRole('admin')) {
            $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
        }

        return $q->get();
    }

    private function applyFilters($query): void
    {
        $query->where('pagos.estado', 'cerrado');

        if (!auth()->user()->hasRole('admin')) {
            $query->join('contratos', 'pagos.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', auth()->user()->regional_id);
        }

        if ($this->contrato_id) {
            $query->where('pagos.contrato_id', $this->contrato_id);
        }
        if ($this->fecha_inicio) {
            $query->where('pagos.fecha', '>=', $this->fecha_inicio);
        }
        if ($this->fecha_fin) {
            $query->where('pagos.fecha', '<=', $this->fecha_fin);
        }
    }

    private function baseQuery()
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

        return DB::table('pagos')
            ->join('detalle_pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->join('facturas', 'facturas.id', '=', 'detalle_pagos.factura_id')
            ->join('proveedors', 'proveedors.id', '=', 'facturas.proveedor_id')
            ->join('movirubros', 'movirubros.id', '=', 'detalle_pagos.movirubro_id')
            ->join('registros', 'registros.id', '=', 'movirubros.registro_id')
            ->leftJoinSub($retencionesSubquery, 'ret_sub', 'ret_sub.factura_id', '=', 'facturas.id');
    }

    private function sumByRetencion($query): \Illuminate\Support\Collection
    {
        $baseQuery = clone $query;
        $this->applyFilters($baseQuery);

        $result = $baseQuery
            ->select(
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
            $row = (array) $row;
            $totals = $invoiceTotals->get($row['factura_id']);
            $row['subtotal'] = $totals->subtotal ?? 0;
            $row['iva'] = $totals->iva ?? 0;
            $row['total_sin_retenciones'] = ($totals->subtotal ?? 0) + ($totals->iva ?? 0);
            $row['total'] = $row['total_sin_retenciones'] - $row['total_retenciones'];
            $row['proveedor_nombre'] = $proveedores[$row['proveedor_id']] ?? '-';
            $row['proveedor_nit'] = $row['proveedor_nit'] ?? '-';
            $row['numero_reg'] = $row['numero_reg'] ?? '-';
            $partes = explode('-', $row['factura_numero'] ?? '');
            $row['factura_numero'] = $partes[1] ?? $row['factura_numero'];
            return $row;
        });
    }

    #[Computed]
    public function reportePagos()
    {
        if (!$this->contrato_id) {
            return collect();
        }

        $q = $this->baseQuery();
        return $this->sumByRetencion($q);
    }

    #[Computed]
    public function resumenGeneral()
    {
        if (!$this->contrato_id) {
            return (object) [
                'total_facturas' => 0,
                'sum_subtotal' => 0,
                'sum_iva' => 0,
                'sum_retenciones' => 0,
                'sum_total' => 0,
            ];
        }

        $query = DB::table('pagos')
            ->join('detalle_pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.estado', 'cerrado');

        if (!auth()->user()->hasRole('admin')) {
            $query->join('contratos', 'pagos.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', auth()->user()->regional_id);
        }

        if ($this->contrato_id) {
            $query->where('pagos.contrato_id', $this->contrato_id);
        }
        if ($this->fecha_inicio) {
            $query->where('pagos.fecha', '>=', $this->fecha_inicio);
        }
        if ($this->fecha_fin) {
            $query->where('pagos.fecha', '<=', $this->fecha_fin);
        }

        $facturaIds = $query->distinct()->pluck('detalle_pagos.factura_id')->toArray();

        if (empty($facturaIds)) {
            return (object) [
                'total_facturas' => 0,
                'sum_subtotal' => 0,
                'sum_iva' => 0,
                'sum_retenciones' => 0,
                'sum_total' => 0,
            ];
        }

        $invoiceTotals = DB::table('factura_lineas')
            ->whereIn('factura_id', $facturaIds)
            ->select(
                DB::raw('SUM(valor_base) as subtotal'),
                DB::raw('SUM(valor_iva) as iva'),
                DB::raw('SUM(valor_con_iva) as total')
            )->first();

        $sumRetenciones = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->whereIn('factura_lineas.factura_id', $facturaIds)
            ->sum('factura_linea_retenciones.valor_retenido');

        return (object) [
            'total_facturas' => count($facturaIds),
            'sum_subtotal' => $invoiceTotals->subtotal ?? 0,
            'sum_iva' => $invoiceTotals->iva ?? 0,
            'sum_retenciones' => $sumRetenciones ?? 0,
            'sum_total' => ($invoiceTotals->total ?? 0) - ($sumRetenciones ?? 0),
        ];
    }
};

?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Reporte de Pagos con Retenciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Detalle de retenciones aplicadas a facturas dentro de cada pago.</p>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contrato <span class="text-rose-500">*</span></label>
                @php
                    $contratosJson = $this->contratos->map(fn($c) => ['id' => $c->id, 'label' => $c->numcontrato . ' — ' . ($c->proveedor->nombre ?? '')])->values()->toArray();
                @endphp
                <div x-data="{ open: false, search: '', selectedId: @js($contrato_id), selectedName: @js($contrato_id ? ($this->contratos->firstWhere('id', $contrato_id)?->numcontrato . ' — ' . ($this->contratos->firstWhere('id', $contrato_id)?->proveedor->nombre ?? '')) : ''), allItems: @js($contratosJson) }"
                     wire:ignore
                     @click.outside="open = false" class="relative">
                    <button type="button" @click="open = !open; search = ''" class="form-input w-full cursor-pointer flex items-center justify-between min-h-[38px] text-left">
                        <span x-text="selectedName || 'Seleccione un contrato'" :class="selectedId ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        <div class="p-2 sticky top-0 bg-white dark:bg-gray-800 z-10">
                            <input type="text" x-model="search" @click.stop placeholder="Escriba para buscar..." class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400" />
                        </div>
                        <template x-for="(item, idx) in (search ? allItems.filter(x => x.label.toLowerCase().includes(search.toLowerCase())) : allItems)" :key="idx">
                            <div @click.stop="selectedId = item.id; selectedName = item.label; open = false; search = ''; $wire.set('contrato_id', item.id)" class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 cursor-pointer" @click.stop>
                                <span x-text="item.label"></span>
                            </div>
                        </template>
                        <div x-show="(search ? allItems.filter(x => x.label.toLowerCase().includes(search.toLowerCase())) : allItems).length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">No se encontraron contratos</div>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Inicio *</label>
                <input type="date" wire:model.live="fecha_inicio" class="form-input w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Fin *</label>
                <input type="date" wire:model.live="fecha_fin" class="form-input w-full" />
            </div>
        </div>
    </div>

    @if ($contrato_id && $fecha_inicio && $fecha_fin)
        {{-- Botones de exportación --}}
        <div class="flex justify-end gap-3 mb-6">
            <a href="{{ route('reportes.pagos.retenciones.excel', ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin, 'contrato_id' => $contrato_id]) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar Excel
            </a>
            <a href="{{ route('reportes.pagos.retenciones.pdf', ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin, 'contrato_id' => $contrato_id]) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar PDF
            </a>
        </div>

        {{-- Resumen General --}}
        @php $resumen = $this->resumenGeneral; @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Facturas</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($resumen->total_facturas) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Subtotal</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">${{ number_format($resumen->sum_subtotal, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">IVA</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">${{ number_format($resumen->sum_iva, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-violet-500 uppercase">Total Sin Ret.</p>
                <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">${{ number_format($resumen->sum_subtotal + $resumen->sum_iva, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-rose-500 uppercase">Total Retenciones</p>
                <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">${{ number_format($resumen->sum_retenciones, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Neto</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">${{ number_format($resumen->sum_total, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Tabla de datos --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">N° Pago</th>
                        <th class="px-4 py-3 text-left">Fecha Pago</th>
                        <th class="px-4 py-3 text-left">N° Factura</th>
                        <th class="px-4 py-3 text-left">Proveedor</th>
                        <th class="px-4 py-3 text-left">NIT</th>
                        <th class="px-4 py-3 text-left">N° Registro</th>
                        <th class="px-4 py-3 text-left">Fecha Factura</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">IVA</th>
                        <th class="px-4 py-3 text-right text-violet-600 dark:text-violet-400">Total Sin Ret.</th>
                        <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Retefuente</th>
                        <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Reteiva</th>
                        <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Reteica</th>
                        <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">Fedepapa</th>
                        <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">Asohofrucol</th>
                        <th class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">Estampilla</th>
                        <th class="px-4 py-3 text-right text-rose-600 dark:text-rose-400">Total Ret.</th>
                        <th class="px-4 py-3 text-right">Total Neto</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->reportePagos as $fila)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $fila['pago_numero'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ \Carbon\Carbon::parse($fila['pago_fecha'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $fila['factura_numero'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $fila['proveedor_nombre'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $fila['proveedor_nit'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $fila['numero_reg'] }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ \Carbon\Carbon::parse($fila['factura_fecha'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['subtotal'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['iva'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-violet-600 dark:text-violet-400">${{ number_format($fila['total_sin_retenciones'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['retefuente'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteiva'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteica'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['fedepapa'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['asohofrucol'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($fila['estampilla'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">${{ number_format($fila['total_retenciones'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['total'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay datos de pagos con retenciones en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->reportePagos->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
                        <tr class="bg-gray-50 dark:bg-gray-700/50">
                            <td colspan="7" class="px-4 py-3 font-bold text-gray-800 dark:text-gray-100">Totales</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-gray-100">${{ number_format($this->reportePagos->sum('subtotal'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-gray-100">${{ number_format($this->reportePagos->sum('iva'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-violet-600 dark:text-violet-400">${{ number_format($this->reportePagos->sum('total_sin_retenciones'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-sky-600 dark:text-sky-400">${{ number_format($this->reportePagos->sum('retefuente'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-sky-600 dark:text-sky-400">${{ number_format($this->reportePagos->sum('reteiva'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-sky-600 dark:text-sky-400">${{ number_format($this->reportePagos->sum('reteica'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-amber-600 dark:text-amber-400">${{ number_format($this->reportePagos->sum('fedepapa'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-amber-600 dark:text-amber-400">${{ number_format($this->reportePagos->sum('asohofrucol'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($this->reportePagos->sum('estampilla'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-rose-600 dark:text-rose-400">${{ number_format($this->reportePagos->sum('total_retenciones'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800 dark:text-gray-100">${{ number_format($this->reportePagos->sum('total'), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-12 text-center">
            <p class="text-gray-500 dark:text-gray-400">Selecciona un contrato y las fechas de inicio y fin para generar el reporte.</p>
        </div>
    @endif
</div>
