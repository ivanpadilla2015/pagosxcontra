<?php

use App\Models\Contrato;
use App\Models\Pago;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $numcontrato = '';
    public ?Contrato $contrato = null;
    public bool $buscado = false;
    public string $search = '';

    public function buscarContrato(): void
    {
        $this->resetValidation();

        if (empty(trim($this->numcontrato))) {
            session()->flash('error', 'Ingrese un número de contrato.');
            return;
        }

        $this->contrato = Contrato::where('numcontrato', trim($this->numcontrato))->first();

        if (!$this->contrato) {
            session()->flash('error', 'No se encontró el contrato con número: ' . $this->numcontrato);
            $this->buscado = true;
            return;
        }

        $this->buscado = true;
    }

    #[Computed]
    public function pagos()
    {
        if (!$this->contrato) {
            return collect();
        }

        return Pago::with(['detalles.factura'])
            ->where('contrato_id', $this->contrato->id)
            ->when($this->search, function ($q) {
                $q->where('numero', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->get();
    }

    public function limpiar(): void
    {
        $this->numcontrato = '';
        $this->contrato = null;
        $this->buscado = false;
        $this->search = '';
        $this->resetValidation();
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Imprimir Pagos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ingrese el número de contrato para ver sus pagos.</p>
        </div>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- Buscador de contrato --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <form wire:submit="buscarContrato" class="flex items-end gap-4">
            <div class="flex-1 max-w-xs">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Contrato</label>
                <input type="text" wire:model="numcontrato" placeholder="Ej: 001-2026" class="form-input w-full" autofocus />
            </div>
            <button type="submit" class="btn bg-violet-500 hover:bg-violet-600 text-white">
                <svg class="w-4 h-4 fill-current mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/></svg>
                Buscar
            </button>
            @if($contrato || $buscado)
                <button type="button" wire:click="limpiar" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Limpiar</button>
            @endif
        </form>
    </div>

    {{-- Información del contrato y listado de pagos --}}
    @if($contrato)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Datos del Contrato</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Número:</span>
                    <span class="ml-2 font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Proveedor:</span>
                    <span class="ml-2 font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Saldo:</span>
                    <span class="ml-2 font-semibold text-gray-800 dark:text-gray-100">${{ number_format($contrato->saldo, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Pagos del Contrato</h3>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por número de pago..." class="form-input w-full max-w-xs" />
            </div>
            <table class="table-auto w-full">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Número</th>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-right">Valor Total</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->pagos as $pago)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $pago->numero }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $pago->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($pago->valor_total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($pago->estado === 'cerrado')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Cerrado</span>
                                @elseif($pago->estado === 'abierto')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Abierto</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Anulado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($pago->estado === 'cerrado')
                                    <button type="button" onclick="window.open('{{ url('pagos/imprimir/' . $pago->id) }}', '_blank')" class="text-violet-500 hover:text-violet-600" title="Imprimir">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                                    </button>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600" title="Solo pagos cerrados">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Este contrato no tiene pagos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif($buscado)
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontró el contrato. Verifique el número e intente nuevamente.</p>
        </div>
    @endif
</div>
