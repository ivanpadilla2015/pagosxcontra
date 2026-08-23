<?php

use App\Models\Factura;
use App\Models\Contrato;
use App\Traits\FiltrablePorRegional;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use FiltrablePorRegional;

    #[Url]
    public string $search = '';

    #[Url]
    public string $desde = '';

    public bool $confirmModalOpen = false;
    public ?int $facturaToActionId = null;
    public string $facturaToActionNumero = '';
    public string $actionType = '';

    #[Computed]
    public function facturas()
    {
        return Factura::with(['proveedor', 'contrato', 'municipio'])
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('contrato.user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->when($this->search, function ($q) {
                $q->where('numero', 'like', '%'.$this->search.'%')
                  ->orWhereHas('proveedor', fn ($q2) => $q2->where('nombre', 'like', '%'.$this->search.'%'))
                  ->orWhereHas('contrato', fn ($q2) => $q2->where('numcontrato', 'like', '%'.$this->search.'%'));
            })
            ->latest()
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function emitir(int $id): void
    {
        $factura = Factura::findOrFail($id);
        if ($factura->estado !== 'borrador') {
            session()->flash('error', 'Solo se pueden emitir facturas en estado borrador.');
            return;
        }
        $factura->update(['estado' => 'emitida']);
        session()->flash('message', 'Factura ' . $factura->numero . ' emitida correctamente.');
    }

    public function confirmAnular(int $id): void
    {
        $factura = Factura::findOrFail($id);
        $this->facturaToActionId = $factura->id;
        $this->facturaToActionNumero = $factura->numero;
        $this->actionType = 'anular';
        $this->confirmModalOpen = true;
    }

    public function confirmAnularPagada(int $id): void
    {
        $factura = Factura::findOrFail($id);
        $this->facturaToActionId = $factura->id;
        $this->facturaToActionNumero = $factura->numero;
        $this->actionType = 'anular_pagada';
        $this->confirmModalOpen = true;
    }

    public function executeAction(): void
    {
        $factura = Factura::findOrFail($this->facturaToActionId);

        if ($this->actionType === 'anular') {
            if ($factura->estado === 'pagada') {
                session()->flash('error', 'No se puede anular una factura pagada. Use la opción de anulación de factura pagada.');
                $this->closeConfirmModal();
                return;
            }
            if ($factura->estado === 'anulada') {
                session()->flash('error', 'La factura ya está anulada.');
                $this->closeConfirmModal();
                return;
            }
            $factura->update(['estado' => 'anulada']);
            session()->flash('message', 'Factura ' . $factura->numero . ' anulada correctamente.');
        }

        if ($this->actionType === 'anular_pagada') {
            if ($factura->estado !== 'pagada') {
                session()->flash('error', 'Esta función es solo para facturas pagadas.');
                $this->closeConfirmModal();
                return;
            }
            $factura->update(['estado' => 'anulada']);
            session()->flash('message', 'Factura pagada ' . $factura->numero . ' anulada. El pago debe revisarse manualmente.');
        }

        $this->closeConfirmModal();
    }

    public function closeConfirmModal(): void
    {
        $this->confirmModalOpen = false;
        $this->facturaToActionId = null;
        $this->facturaToActionNumero = '';
        $this->actionType = '';
    }

    public function getEstadoColor(string $estado): string
    {
        return match ($estado) {
            'borrador' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            'emitida' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'pagada' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'anulada' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            default => 'bg-gray-100 text-gray-700',
        };
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Facturas</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Listado de facturas registradas.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por número, proveedor o contrato..." class="form-input w-full max-w-xs" />
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Número</th>
                    <th class="px-4 py-3 text-left">Proveedor</th>
                    <th class="px-4 py-3 text-left">Contrato</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->facturas as $factura)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ explode('-', $factura->numero)[1] ?? $factura->numero }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->proveedor->nombre ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->contrato->numcontrato ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($factura->subtotal + $factura->total_iva, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getEstadoColor($factura->estado) }}">
                                {{ ucfirst($factura->estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($factura->estado === 'borrador')
                                <a href="{{ $desde === 'facturar' ? route('facturar', $factura->id) : route('facturacion', $factura->id) }}" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                                </a>
                                <button type="button" wire:click="emitir({{ $factura->id }})" class="text-emerald-500 hover:text-emerald-600 mr-3" title="Emitir">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5"/></svg>
                                </button>
                                <button type="button" wire:click="confirmAnular({{ $factura->id }})" class="text-rose-500 hover:text-rose-600" title="Anular">
                                    <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                                </button>
                            @elseif ($factura->estado === 'emitida')
                                <span class="text-xs text-gray-400 dark:text-gray-500">Esperando pago</span>
                            @elseif ($factura->estado === 'pagada')
                                <button type="button" wire:click="confirmAnularPagada({{ $factura->id }})" class="text-amber-500 hover:text-amber-600" title="Anular factura pagada">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                </button>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Anulada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay facturas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->facturas->links() }}</div>

    @if ($confirmModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeConfirmModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full {{ $actionType === 'anular_pagada' ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-rose-100 dark:bg-rose-900/30' }}">
                    <svg class="w-6 h-6 {{ $actionType === 'anular_pagada' ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">
                    {{ $actionType === 'anular_pagada' ? 'Anular Factura Pagada' : 'Anular Factura' }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">
                    @if ($actionType === 'anular_pagada')
                        ¿Estás seguro de anular la factura <span class="font-semibold">{{ $facturaToActionNumero }}</span>? El pago asociado deberá revisarse manualmente.
                    @else
                        ¿Estás seguro de anular la factura <span class="font-semibold">{{ $facturaToActionNumero }}</span>?
                    @endif
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeConfirmModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="executeAction" class="btn {{ $actionType === 'anular_pagada' ? 'bg-amber-600 hover:bg-amber-700 text-white border border-amber-600' : 'bg-rose-600 hover:bg-rose-700 text-white border border-rose-600' }}">
                        {{ $actionType === 'anular_pagada' ? 'Anular Pagada' : 'Anular' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
