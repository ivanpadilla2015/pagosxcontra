<?php

use Livewire\Component;
use App\Models\Contrato;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'numcontrato';
    public $sortDirection = 'asc';
    public $expandedContrato = null;

    #[Computed]
    public function contratos()
    {
        $regionalId = auth()->user()->regional_id;

        return Contrato::with(['proveedor', 'registros.movirubros.rubro', 'registros.tiporegistro'])
            ->whereHas('user', function ($q) use ($regionalId) {
                $q->where('regional_id', $regionalId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('numcontrato', 'like', '%' . $this->search . '%')
                        ->orWhereHas('proveedor', fn ($pq) => $pq->where('nombre', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);
    }

    public function getValorTotalProperty($contrato)
    {
        return $contrato->registros->flatMap->movirubros->sum('valor_rubro');
    }

    public function getSaldoTotalProperty($contrato)
    {
        return $contrato->registros->flatMap->movirubros->sum('saldo_rubro');
    }

    public function toggleExpand($contratoId)
    {
        $this->expandedContrato = $this->expandedContrato === $contratoId ? null : $contratoId;
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Reportes</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Reporte detallado de contratos con valor y saldo</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700">
            {{-- Leyenda de estados --}}
            <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4 text-xs">
                <span class="text-gray-500 dark:text-gray-400 font-medium">Estados del saldo:</span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">Sano</span> (≥75%)
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                    <span class="text-orange-600 dark:text-orange-400 font-medium">Moderado</span> (50-74%)
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span class="text-red-600 dark:text-red-400 font-medium">Crítico</span> (<50%)
                </span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="w-full sm:max-w-xs">
                    <x-input type="text" wire:model.live="search" placeholder="Buscar por contrato o proveedor..." />
                </div>
                <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                            Total Contratos: {{ $this->contratos->total() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-6 py-4 text-left w-8"></th>
                            <th wire:click="sortBy('numcontrato')" class="cursor-pointer px-6 py-4 text-left">
                                N° Contrato
                                @if ($sortField === 'numcontrato')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-left">Proveedor</th>
                            <th class="px-6 py-4 text-left">Objeto</th>
                            <th wire:click="sortBy('fecha_inicio_contrato')" class="cursor-pointer px-6 py-4 text-left">
                                Fecha Inicio
                                @if ($sortField === 'fecha_inicio_contrato')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th wire:click="sortBy('fecha_fin_contrato')" class="cursor-pointer px-6 py-4 text-left">
                                Fecha Fin
                                @if ($sortField === 'fecha_fin_contrato')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-right">Valor Total</th>
                            <th class="px-6 py-4 text-right">Saldo</th>
                            <th class="px-6 py-4 text-center">% Saldo</th>
                            <th class="px-6 py-4 text-center">Estado Saldo</th>
                            <th class="px-6 py-4 text-center">N° Registros</th>
                            <th class="px-6 py-4 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->contratos as $contrato)
                            @php
                                $valorTotal = $contrato->registros->flatMap->movirubros->sum('valor_rubro');
                                $saldoTotal = $contrato->registros->flatMap->movirubros->sum('saldo_rubro');
                                $numRegistros = $contrato->registros->count();
                                $isExpanded = $this->expandedContrato === $contrato->id;
                                $porcentajeSaldo = $valorTotal > 0 ? ($saldoTotal / $valorTotal) * 100 : 0;

                                if ($porcentajeSaldo >= 75) {
                                    $saldoColor = 'text-emerald-600 dark:text-emerald-400';
                                    $saldoBg = 'bg-emerald-50 dark:bg-emerald-900/20';
                                    $saldoBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
                                    $saldoLabel = 'Sano';
                                } elseif ($porcentajeSaldo >= 50) {
                                    $saldoColor = 'text-orange-600 dark:text-orange-400';
                                    $saldoBg = 'bg-orange-50 dark:bg-orange-900/20';
                                    $saldoBadge = 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
                                    $saldoLabel = 'Moderado';
                                } else {
                                    $saldoColor = 'text-red-600 dark:text-red-400';
                                    $saldoBg = 'bg-red-50 dark:bg-red-900/20';
                                    $saldoBadge = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                                    $saldoLabel = 'Crítico';
                                }
                            @endphp
                            {{-- Fila principal del contrato --}}
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $isExpanded ? 'bg-violet-50/50 dark:bg-violet-900/10' : $saldoBg }}">
                                <td class="px-6 py-4">
                                    <button wire:click="toggleExpand({{ $contrato->id }})" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition" title="Ver detalles">
                                        <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">
                                    {{ $contrato->numcontrato }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $contrato->proveedor->nombre ?? '-' }}
                                </td>
                                <td class="px-6 py-4 max-w-xs truncate" title="{{ $contrato->objetocontrato }}">
                                    {{ $contrato->objetocontrato ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $contrato->fecha_inicio_contrato?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $contrato->fecha_fin_contrato?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-semibold text-gray-900 dark:text-gray-100">
                                    ${{ number_format($valorTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-semibold {{ $saldoColor }}">
                                    ${{ number_format($saldoTotal, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $saldoBadge }}">
                                        {{ number_format($porcentajeSaldo, 0) }}%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $saldoBadge }}">
                                        {{ $saldoLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $numRegistros }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($contrato->fecha_fin_contrato && $contrato->fecha_fin_contrato->isPast())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                            Vencido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Vigente
                                        </span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Fila expandida: Desglose por registros y rubros --}}
                            @if($isExpanded)
                                <tr>
                                    <td colspan="12" class="px-6 py-0">
                                        <div class="py-4 pl-8 border-l-2 border-violet-300 dark:border-violet-600 ml-2">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Desglose por Registros y Rubros</h4>

                                            @forelse ($contrato->registros as $registro)
                                                <div class="mb-4 last:mb-0">
                                                    <div class="flex items-center gap-3 mb-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                            @if($registro->tiporegistro_id == 1) bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                            @elseif($registro->tiporegistro_id == 2) bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                                            @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                                            @endif">
                                                            {{ $registro->tiporegistro->name ?? '-' }}
                                                        </span>
                                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            N° {{ $registro->numero_reg }}
                                                        </span>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            ({{ $registro->fecha_reg?->format('d/m/Y') ?? '-' }})
                                                        </span>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            Valor: ${{ number_format($registro->movirubros->sum('valor_rubro'), 0, ',', '.') }}
                                                        </span>
                                                    </div>

                                                    @if($registro->movirubros->count() > 0)
                                                        <div class="overflow-x-auto ml-4">
                                                            <table class="w-full text-xs">
                                                                <thead>
                                                                    <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                                        <th class="px-3 py-1.5 text-left">Código</th>
                                                                        <th class="px-3 py-1.5 text-left">Rubro</th>
                                                                        <th class="px-3 py-1.5 text-right">Valor</th>
                                                                        <th class="px-3 py-1.5 text-right">Saldo</th>
                                                                        <th class="px-3 py-1.5 text-left">Dependencia</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                                                    @foreach ($registro->movirubros as $movirubro)
                                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                                            <td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">
                                                                                {{ $movirubro->rubro->codigo_rubro ?? '-' }}
                                                                            </td>
                                                                            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">
                                                                                {{ $movirubro->rubro->nombre_rubro ?? '-' }}
                                                                            </td>
                                                                            <td class="px-3 py-1.5 text-right text-gray-900 dark:text-gray-100">
                                                                                ${{ number_format($movirubro->valor_rubro, 2, ',', '.') }}
                                                                            </td>
                                                                            @php
                                                                                $pctRubro = $movirubro->valor_rubro > 0 ? ($movirubro->saldo_rubro / $movirubro->valor_rubro) * 100 : 0;
                                                                                $colorRubro = $pctRubro >= 75 ? 'text-emerald-600 dark:text-emerald-400'
                                                                                    : ($pctRubro >= 50 ? 'text-orange-600 dark:text-orange-400'
                                                                                    : 'text-red-600 dark:text-red-400');
                                                                            @endphp
                                                                            <td class="px-3 py-1.5 text-right {{ $colorRubro }}">
                                                                                ${{ number_format($movirubro->saldo_rubro, 2, ',', '.') }}
                                                                            </td>
                                                                            <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400">
                                                                                {{ $movirubro->dependencia_afectacion ?? '-' }}
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="font-semibold text-gray-700 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                                                                        <td colspan="2" class="px-3 py-1.5 text-right">Subtotal:</td>
                                                                        <td class="px-3 py-1.5 text-right">${{ number_format($registro->movirubros->sum('valor_rubro'), 2, ',', '.') }}</td>
                                                                        <td class="px-3 py-1.5 text-right">${{ number_format($registro->movirubros->sum('saldo_rubro'), 2, ',', '.') }}</td>
                                                                        <td></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <div class="ml-4 text-xs text-gray-400 dark:text-gray-500">
                                                            Sin movimientos de rubros registrados.
                                                        </div>
                                                    @endif
                                                </div>
                                            @empty
                                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                                    Este contrato no tiene registros asociados.
                                                </div>
                                            @endforelse

                                            {{-- Totales del contrato --}}
                                            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center gap-6">
                                                <div class="text-sm">
                                                    <span class="text-gray-500 dark:text-gray-400">Valor Total:</span>
                                                    <span class="ml-2 font-bold text-gray-900 dark:text-gray-100">${{ number_format($valorTotal, 0, ',', '.') }}</span>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="text-gray-500 dark:text-gray-400">Saldo Actual:</span>
                                                    <span class="ml-2 font-bold {{ $saldoColor }}">${{ number_format($saldoTotal, 0, ',', '.') }}</span>
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $saldoBadge }}">
                                                        {{ number_format($porcentajeSaldo, 0) }}% - {{ $saldoLabel }}
                                                    </span>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="text-gray-500 dark:text-gray-400">Ejecutado:</span>
                                                    <span class="ml-2 font-bold text-amber-600 dark:text-amber-400">
                                                        ${{ number_format($valorTotal - $saldoTotal, 0, ',', '.') }}
                                                        ({{ $valorTotal > 0 ? number_format((($valorTotal - $saldoTotal) / $valorTotal) * 100, 1) : '0' }}%)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron contratos para la regional actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    {{-- Fila de totales generales --}}
                    @if($this->contratos->count() > 0)
                        @php
                            $allContratos = $this->contratos->getCollection();
                            $totalValor = 0;
                            $totalSaldo = 0;
                            foreach ($allContratos as $c) {
                                $totalValor += $c->registros->flatMap->movirubros->sum('valor_rubro');
                                $totalSaldo += $c->registros->flatMap->movirubros->sum('saldo_rubro');
                            }
                        @endphp
                        <tfoot>
                            <tr class="font-bold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700/50 border-t-2 border-gray-200 dark:border-gray-600">
                                <td colspan="6" class="px-6 py-4 text-right">TOTALES:</td>
                                <td class="px-6 py-4 text-right">${{ number_format($totalValor, 0, ',', '.') }}</td>
                                @php
                                    $pctTotal = $totalValor > 0 ? ($totalSaldo / $totalValor) * 100 : 0;
                                    $colorTotal = $pctTotal >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($pctTotal >= 50 ? 'text-orange-600 dark:text-orange-400' : 'text-red-600 dark:text-red-400');
                                @endphp
                                <td class="px-6 py-4 text-right {{ $colorTotal }}">${{ number_format($totalSaldo, 0, ',', '.') }}</td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->contratos->links() }}
            </div>
        </div>
    </div>
</div>
