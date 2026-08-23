<?php

use App\Models\Contrato;
use App\Models\Proveedor;
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
    public ?int $proveedor_id = null;

    #[Url]
    public ?int $contrato_id = null;

    #[Url]
    public string $tab = 'contrato';

    public array $abiertos = [];

    #[Computed]
    public function proveedores()
    {
        return Proveedor::orderBy('nombre')->get();
    }

    #[Computed]
    public function contratos()
    {
        $q = Contrato::with('proveedor')->orderBy('numcontrato');

        if (!auth()->user()->hasRole('admin')) {
            $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
        }

        if ($this->proveedor_id) {
            $q->where('proveedor_id', $this->proveedor_id);
        }

        return $q->get();
    }

    private function applyFilters($query): void
    {
        $query->where('facturas.estado', '!=', 'anulada');

        if (!auth()->user()->hasRole('admin')) {
            $query->join('contratos', 'facturas.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', auth()->user()->regional_id);
        }

        if ($this->fecha_inicio) {
            $query->where('facturas.fecha', '>=', $this->fecha_inicio);
        }
        if ($this->fecha_fin) {
            $query->where('facturas.fecha', '<=', $this->fecha_fin);
        }
        if ($this->proveedor_id) {
            $query->where('facturas.proveedor_id', $this->proveedor_id);
        }
        if ($this->contrato_id) {
            $query->where('facturas.contrato_id', $this->contrato_id);
        }
    }

    private function baseQuery()
    {
        return DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id');
    }

    private function pickColumn(): string
    {
        return match ($this->tab) {
            'contrato' => 'facturas.contrato_id',
            'proveedor' => 'facturas.proveedor_id',
            'factura' => 'facturas.id',
            default => 'facturas.contrato_id',
        };
    }

    private function sumByRetencion($query, string $groupBy): \Illuminate\Support\Collection
    {
        $baseQuery = clone $query;
        $this->applyFilters($baseQuery);

        $isAdmin = auth()->user()->hasRole('admin');
        $regionalId = auth()->user()->regional_id;

        $invoiceTotals = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->when(!$isAdmin, function ($q) use ($regionalId) {
                $q->join('contratos', 'facturas.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', $regionalId);
            })
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

        $result = $query
            ->select(
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

        return $result->map(function ($row) use ($invoiceTotals) {
            $totals = $invoiceTotals->get($row->grupo_id);
            $row->sum_subtotal = $totals->subtotal ?? 0;
            $row->sum_iva = $totals->iva ?? 0;
            $row->sum_total = $totals->total ?? 0;
            return $row;
        });
    }

    #[Computed]
    public function porContrato()
    {
        $q = $this->baseQuery();
        $this->applyFilters($q);
        $result = $this->sumByRetencion($q, 'facturas.contrato_id');

        return $result->map(fn($row) => (array) $row + [
            'contrato' => Contrato::with('proveedor')->find($row->grupo_id),
        ]);
    }

    #[Computed]
    public function porProveedor()
    {
        $q = $this->baseQuery();
        $this->applyFilters($q);
        $result = $this->sumByRetencion($q, 'facturas.proveedor_id');

        return $result->map(fn($row) => (array) $row + [
            'proveedor' => Proveedor::find($row->grupo_id),
        ]);
    }

    #[Computed]
    public function porFactura()
    {
        $q = $this->baseQuery();
        $this->applyFilters($q);

        $paginator = $q->select(
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
            ->paginate(20);

        $paginator->setCollection($paginator->getCollection()->map(function ($row) {
            $row = (array) $row;
            $partes = explode('-', $row['numero'] ?? '');
            $row['numero'] = $partes[1] ?? $row['numero'];
            return $row;
        }));

        return $paginator;
    }

    #[Computed]
    public function resumenGeneral()
    {
        $q = $this->baseQuery();
        $this->applyFilters($q);

        $isAdmin = auth()->user()->hasRole('admin');
        $regionalId = auth()->user()->regional_id;

        $invoiceTotals = DB::table('factura_lineas')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->when(!$isAdmin, function ($q2) use ($regionalId) {
                $q2->join('contratos', 'facturas.contrato_id', '=', 'contratos.id')
                   ->join('users', 'contratos.user_id', '=', 'users.id')
                   ->where('users.regional_id', $regionalId);
            })
            ->where('facturas.estado', '!=', 'anulada')
            ->select(
                DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
                DB::raw('SUM(factura_lineas.valor_iva) as iva'),
                DB::raw('SUM(factura_lineas.valor_con_iva) as total')
            )
            ->first();

        $retentionTotals = $q->select(
                DB::raw('COUNT(DISTINCT facturas.id) as total_facturas'),
                DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'general' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_general"),
                DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'parafiscal' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_parafiscal"),
                DB::raw("COALESCE(SUM(CASE WHEN retenciones.tipo = 'territorial' THEN factura_linea_retenciones.valor_retenido ELSE 0 END), 0) as ret_territorial"),
                DB::raw('COALESCE(SUM(factura_linea_retenciones.valor_retenido), 0) as sum_retenciones')
            )
            ->first();

        $retentionTotals->sum_subtotal = $invoiceTotals->subtotal ?? 0;
        $retentionTotals->sum_iva = $invoiceTotals->iva ?? 0;
        $retentionTotals->sum_total = $invoiceTotals->total ?? 0;

        return $retentionTotals;
    }

    public function toggle(int $idx): void
    {
        if (in_array($idx, $this->abiertos)) {
            $this->abiertos = array_diff($this->abiertos, [$idx]);
        } else {
            $this->abiertos[] = $idx;
        }
    }

    public function estaAbierto(int $idx): bool
    {
        return in_array($idx, $this->abiertos);
    }
};

?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Reporte de Retenciones</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Retenciones aplicadas a proveedores por contrato, proveedor y factura.</p>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Inicio *</label>
                <input type="date" wire:model.live="fecha_inicio" class="form-input w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Fin *</label>
                <input type="date" wire:model.live="fecha_fin" class="form-input w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                <select wire:model.live="proveedor_id" class="form-input w-full">
                    <option value="">Todos</option>
                    @foreach ($this->proveedores as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contrato</label>
                <select wire:model.live="contrato_id" class="form-input w-full">
                    <option value="">Todos</option>
                    @foreach ($this->contratos as $c)
                        <option value="{{ $c->id }}">{{ $c->numcontrato }} — {{ $c->proveedor->nombre ?? '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($fecha_inicio && $fecha_fin)
        {{-- Botones de exportación --}}
        <div class="flex justify-end gap-3 mb-6">
            <a href="{{ route('reportes.retenciones.excel', ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin, 'proveedor_id' => $proveedor_id, 'contrato_id' => $contrato_id, 'tab' => $tab]) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar Excel
            </a>
            <a href="{{ route('reportes.retenciones.pdf', ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin, 'proveedor_id' => $proveedor_id, 'contrato_id' => $contrato_id, 'tab' => $tab]) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium transition">
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
                <p class="text-xs text-sky-500 uppercase">General</p>
                <p class="text-2xl font-bold text-sky-600 dark:text-sky-400">${{ number_format($resumen->ret_general, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-amber-500 uppercase">Parafiscal</p>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">${{ number_format($resumen->ret_parafiscal, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-emerald-500 uppercase">Territorial</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($resumen->ret_territorial, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
                <p class="text-xs text-rose-500 uppercase">Total Retenido</p>
                <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">${{ number_format($resumen->sum_retenciones, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700/60 mb-6">
            <button wire:click="$set('tab', 'contrato')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'contrato' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Por Contrato</button>
            <button wire:click="$set('tab', 'proveedor')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'proveedor' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Por Proveedor</button>
            <button wire:click="$set('tab', 'factura')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'factura' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Por Factura</button>
        </div>

        {{-- Tab: Por Contrato --}}
        @if ($tab === 'contrato')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">Contrato</th>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-right">Fact.</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
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
                        @forelse ($this->porContrato as $idx => $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $fila['contrato']->numcontrato ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $fila['contrato']->proveedor->nombre ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ $fila['total_facturas'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_subtotal'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_subtotal'] + $fila['sum_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['retefuente'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteiva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteica'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['fedepapa'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['asohofrucol'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($fila['estampilla'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">${{ number_format($fila['total_retenciones'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_total'] - $fila['total_retenciones'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay datos de retenciones en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Tab: Por Proveedor --}}
        @if ($tab === 'proveedor')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-right">Fact.</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
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
                        @forelse ($this->porProveedor as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $fila['proveedor']->nombre ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ $fila['total_facturas'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_subtotal'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_subtotal'] + $fila['sum_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['retefuente'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteiva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteica'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['fedepapa'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['asohofrucol'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($fila['estampilla'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">${{ number_format($fila['total_retenciones'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['sum_total'] - $fila['total_retenciones'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay datos de retenciones en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Tab: Por Factura --}}
        @if ($tab === 'factura')
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">Número</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-left">Contrato</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Retefuente</th>
                            <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Reteiva</th>
                            <th class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">Reteica</th>
                            <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">Fedepapa</th>
                            <th class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">Asohofrucol</th>
                            <th class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">Estampilla</th>
                            <th class="px-4 py-3 text-right text-rose-600 dark:text-rose-400">Total Ret.</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse ($this->porFactura as $fila)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $fila['numero'] }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ Proveedor::find($fila['proveedor_id'])->nombre ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ Contrato::find($fila['contrato_id'])->numcontrato ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['subtotal'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($fila['total_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['subtotal'] + $fila['total_iva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['retefuente'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteiva'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sky-600 dark:text-sky-400">${{ number_format($fila['reteica'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['fedepapa'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format($fila['asohofrucol'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($fila['estampilla'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">${{ number_format($fila['total_retenciones'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($fila['subtotal'] + $fila['total_iva'] - $fila['total_retenciones'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay facturas con retenciones en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $this->porFactura->links() }}</div>
        @endif
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-12 text-center">
            <p class="text-gray-500 dark:text-gray-400">Selecciona las fechas de inicio y fin para generar el reporte.</p>
        </div>
    @endif
</div>
