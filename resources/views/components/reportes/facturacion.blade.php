<?php

use App\Models\Factura;
use App\Models\Proveedor;
use App\Models\Retencion;
use App\Traits\FiltrablePorRegional;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reportes de facturación: totales por factura, proveedor, retención y período.
 */
new class extends Component
{
    use FiltrablePorRegional;
    /** Fecha inicio del filtro. */
    #[Url]
    public ?string $fecha_inicio = null;

    /** Fecha fin del filtro. */
    #[Url]
    public ?string $fecha_fin = null;

    /** Filtro por proveedor. */
    #[Url]
    public ?int $proveedor_id = null;

    /** Pestaña activa: facturas, proveedores, retenciones. */
    #[Url]
    public string $tab = 'facturas';

    /** Lista de proveedores para el select. */
    #[Computed]
    public function proveedores()
    {
        return Proveedor::orderBy('nombre')->get();
    }

    /** Reporte de facturas con filtros aplicados. */
    #[Computed]
    public function facturas()
    {
        return Factura::query()
            ->with(['proveedor', 'contrato'])
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('contrato.user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->when($this->fecha_inicio, fn($q) => $q->where('fecha', '>=', $this->fecha_inicio))
            ->when($this->fecha_fin, fn($q) => $q->where('fecha', '<=', $this->fecha_fin))
            ->when($this->proveedor_id, fn($q) => $q->where('proveedor_id', $this->proveedor_id))
            ->latest('fecha')
            ->paginate(15);
    }

    /** Totales agrupados por proveedor. */
    #[Computed]
    public function totalesPorProveedor()
    {
        return Factura::query()
            ->select('proveedor_id', DB::raw('COUNT(*) as total_facturas'), DB::raw('SUM(subtotal) as sum_subtotal'), DB::raw('SUM(total_iva) as sum_iva'), DB::raw('SUM(total_retenciones) as sum_retenciones'), DB::raw('SUM(total) as sum_total'))
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('contrato.user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->when($this->fecha_inicio, fn($q) => $q->where('fecha', '>=', $this->fecha_inicio))
            ->when($this->fecha_fin, fn($q) => $q->where('fecha', '<=', $this->fecha_fin))
            ->when($this->proveedor_id, fn($q) => $q->where('proveedor_id', $this->proveedor_id))
            ->groupBy('proveedor_id')
            ->with('proveedor')
            ->orderByDesc('sum_total')
            ->get();
    }

    /** Totales agrupados por retención. */
    #[Computed]
    public function totalesPorRetencion()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        $regionalId = auth()->user()->regional_id;

        return DB::table('factura_linea_retenciones')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->when(!$isAdmin, function ($q) use ($regionalId) {
                $q->join('contratos', 'facturas.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', $regionalId);
            })
            ->select('retenciones.name as retencion_nombre', 'retenciones.tipo as retencion_tipo', DB::raw('COUNT(*) as total_registros'), DB::raw('SUM(valor_retenido) as sum_retenido'))
            ->when($this->fecha_inicio, fn($q) => $q->where('facturas.fecha', '>=', $this->fecha_inicio))
            ->when($this->fecha_fin, fn($q) => $q->where('facturas.fecha', '<=', $this->fecha_fin))
            ->when($this->proveedor_id, fn($q) => $q->where('facturas.proveedor_id', $this->proveedor_id))
            ->groupBy('retenciones.name', 'retenciones.tipo')
            ->orderByDesc('sum_retenido')
            ->get();
    }

    /** Totales generales del período. */
    #[Computed]
    public function resumenGeneral()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        $regionalId = auth()->user()->regional_id;

        return Factura::query()
            ->select(
                DB::raw('COUNT(*) as total_facturas'),
                DB::raw('COALESCE(SUM(subtotal), 0) as sum_subtotal'),
                DB::raw('COALESCE(SUM(total_iva), 0) as sum_iva'),
                DB::raw('COALESCE(SUM(total_retenciones), 0) as sum_retenciones'),
                DB::raw('COALESCE(SUM(total), 0) as sum_total'),
            )
            ->when(!$isAdmin, function ($q) use ($regionalId) {
                $q->whereHas('contrato.user', fn($q2) => $q2->where('regional_id', $regionalId));
            })
            ->when($this->fecha_inicio, fn($q) => $q->where('fecha', '>=', $this->fecha_inicio))
            ->when($this->fecha_fin, fn($q) => $q->where('fecha', '<=', $this->fecha_fin))
            ->when($this->proveedor_id, fn($q) => $q->where('proveedor_id', $this->proveedor_id))
            ->first();
    }
};

?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Reportes de Facturación</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Consulta totales por factura, proveedor y retención.</p>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Inicio</label>
                <input type="date" wire:model.live="fecha_inicio" class="form-input w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Fin</label>
                <input type="date" wire:model.live="fecha_fin" class="form-input w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                <select wire:model.live="proveedor_id" class="form-input w-full">
                    <option value="">Todos los proveedores</option>
                    @foreach ($this->proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Resumen General --}}
    @php $resumen = $this->resumenGeneral; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
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
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Retenciones</p>
            <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">${{ number_format($resumen->sum_retenciones, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Sin Ret.</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">${{ number_format($resumen->sum_subtotal + $resumen->sum_iva, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Neto</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($resumen->sum_total, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700/60 mb-6">
        <button wire:click="$set('tab', 'facturas')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'facturas' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Facturas</button>
        <button wire:click="$set('tab', 'proveedores')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'proveedores' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Por Proveedor</button>
        <button wire:click="$set('tab', 'retenciones')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $tab === 'retenciones' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Por Retención</button>
    </div>

    {{-- Tab: Facturas --}}
    @if ($tab === 'facturas')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <table class="table-auto w-full">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Número</th>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Proveedor</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">IVA</th>
                        <th class="px-4 py-3 text-right">Retenciones</th>
                        <th class="px-4 py-3 text-right">Total Sin Ret.</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->facturas as $factura)
                        <tr>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 font-medium">{{ explode('-', $factura->numero)[1] ?? $factura->numero }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $factura->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $factura->proveedor->nombre ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($factura->estado === 'borrador')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs px-2 py-0.5">Borrador</span>
                                @elseif ($factura->estado === 'emitida')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5">Emitida</span>
                                @elseif ($factura->estado === 'pagada')
                                    <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs px-2 py-0.5">Pagada</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 text-xs px-2 py-0.5">Anulada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($factura->subtotal, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($factura->total_iva, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-400">${{ number_format($factura->total_retenciones, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($factura->subtotal + $factura->total_iva, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($factura->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay facturas registradas en este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->facturas->links() }}</div>
    @endif

    {{-- Tab: Por Proveedor --}}
    @if ($tab === 'proveedores')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <table class="table-auto w-full">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Proveedor</th>
                        <th class="px-4 py-3 text-right">Facturas</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">IVA</th>
                        <th class="px-4 py-3 text-right">Retenciones</th>
                        <th class="px-4 py-3 text-right">Total Sin Ret.</th>
                        <th class="px-4 py-3 text-right">Total Neto</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->totalesPorProveedor as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 font-medium">{{ $item->proveedor->nombre ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ $item->total_facturas }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($item->sum_subtotal, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($item->sum_iva, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-400">${{ number_format($item->sum_retenciones, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($item->sum_subtotal + $item->sum_iva, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($item->sum_total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay datos para este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Tab: Por Retención --}}
    @if ($tab === 'retenciones')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <table class="table-auto w-full">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Retención</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-right">Registros</th>
                        <th class="px-4 py-3 text-right">Total Retenido</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->totalesPorRetencion as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 font-medium">{{ $item->retencion_nombre }}</td>
                            <td class="px-4 py-3">
                                @if ($item->retencion_tipo === 'general')
                                    <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs px-2 py-0.5">General</span>
                                @elseif ($item->retencion_tipo === 'parafiscal')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs px-2 py-0.5">Parafiscal</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5">Territorial</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ $item->total_registros }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">${{ number_format($item->sum_retenido, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay datos de retenciones para este período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
