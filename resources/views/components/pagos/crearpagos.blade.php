<?php

/**
 * Componente Livewire: Listado de Pagos
 *
 * Ruta: /pagos
 * Funcionalidades:
 *  - Listar pagos con paginación y búsqueda por número de pago o contrato.
 *  - Eliminar pagos en estado "abierto" (borrado físico de detalle + pago).
 *  - Anular pagos en estado "cerrado" (solo el último pago del contrato).
 *    La anulación revierte saldos de movirubros y devuelve facturas a "emitida".
 *
 * Estados de pago:
 *  - abierto:  editable, se pueden agregar/quitar facturas.
 *  - cerrado:  confirmado, saldos ya descontados, facturas en "pagada".
 *  - anulada:  revertido, saldos restaurados, facturas en "emitida".
 */

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\Movirubro;
use App\Models\Pago;
use App\Traits\FiltrablePorRegional;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use FiltrablePorRegional;

    // Búsqueda por número de pago o contrato (sincronizada con URL)
    #[Url]
    public string $search = '';

    // Modal de eliminar pago (solo abierto)
    public bool $confirmModalOpen = false;
    public ?int $pagoToDeleteId = null;
    public string $pagoToDeleteNumero = '';

    // Modal de anular pago (cerrado, último del contrato)
    public bool $confirmAnularModalOpen = false;
    public ?int $pagoToAnularId = null;
    public string $pagoToAnularNumero = '';

    /**
     * Lista pagos paginados con búsqueda.
     * Busca por número de pago o por número de contrato asociado.
     */
    #[Computed]
    public function pagos()
    {
        return Pago::with(['contrato', 'tramitePago'])
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('contrato.user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->when($this->search, function ($q) {
                $q->where('numero', 'like', '%'.$this->search.'%')
                  ->orWhereHas('contrato', fn ($q2) => $q2->where('numero', 'like', '%'.$this->search.'%'));
            })
            ->latest()
            ->paginate(15);
    }

    /** Resetea la paginación al cambiar la búsqueda */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Abre el modal de confirmación para eliminar un pago */
    public function confirmDelete(int $id, string $numero): void
    {
        $this->pagoToDeleteId = $id;
        $this->pagoToDeleteNumero = $numero;
        $this->confirmModalOpen = true;
    }

    /**
     * Elimina un pago (borrado físico).
     * Solo permite pagos en estado "abierto" — los cerrados no se pueden eliminar.
     * Elimina primero los detalles (detalle_pagos) y luego el pago.
     */
    public function executeDelete(): void
    {
        $pago = Pago::findOrFail($this->pagoToDeleteId);

        if ($pago->estado === 'cerrado') {
            session()->flash('error', 'No se puede eliminar un pago cerrado.');
            $this->closeConfirmModal();
            return;
        }

        DB::transaction(function () use ($pago) {
            $pago->detalles()->delete();
            $pago->delete();

            $contrato = Contrato::find($pago->contrato_id);
            if ($contrato && $contrato->cansecu_pagos > 0) {
                $contrato->update(['cansecu_pagos' => $contrato->cansecu_pagos - 1]);
            }
        });

        session()->flash('message', 'Pago ' . $this->pagoToDeleteNumero . ' eliminado correctamente.');
        $this->closeConfirmModal();
    }

    /** Cierra el modal de eliminación */
    public function closeConfirmModal(): void
    {
        $this->confirmModalOpen = false;
        $this->pagoToDeleteId = null;
        $this->pagoToDeleteNumero = '';
    }

    /**
     * Verifica si un pago es el último pago no anulado de su contrato.
     * Compara su número contra el max(numero) de pagos del mismo contrato
     * que no estén en estado "anulada".
     */
    public function esUltimoPago(Pago $pago): bool
    {
        $maxNumero = Pago::where('contrato_id', $pago->contrato_id)
            ->where('estado', '!=', 'anulada')
            ->max('numero');

        return $pago->numero === $maxNumero;
    }

    /** Abre el modal de confirmación para anular un pago cerrado */
    public function confirmAnular(int $id, string $numero): void
    {
        $this->pagoToAnularId = $id;
        $this->pagoToAnularNumero = $numero;
        $this->confirmAnularModalOpen = true;
    }

    /** Cierra el modal de anulación */
    public function closeAnularModal(): void
    {
        $this->confirmAnularModalOpen = false;
        $this->pagoToAnularId = null;
        $this->pagoToAnularNumero = '';
    }

    /**
     * Anula un pago cerrado (solo el último del contrato).
     *
     * Flujo dentro de transacción:
     *  1. Valida que el pago esté en estado "cerrado".
     *  2. Valida que sea el último pago no anulado del contrato.
     *  3. Por cada detalle_pago: suma valor_pagado al saldo_rubro del movirubro
     *     (revierte el descuento que se hizo al confirmar).
     *  4. Cambia estado de las facturas asociadas de "pagada" → "emitida".
     *  5. Cambia estado del pago a "anulada".
     */
    public function executeAnular(): void
    {
        $pago = Pago::with(['detalles.movirubro', 'detalles.factura'])->findOrFail($this->pagoToAnularId);

        if ($pago->estado !== 'cerrado') {
            session()->flash('error', 'Solo se pueden anular pagos cerrados.');
            $this->closeAnularModal();
            return;
        }

        if (!$this->esUltimoPago($pago)) {
            session()->flash('error', 'Solo se puede anular el último pago del contrato.');
            $this->closeAnularModal();
            return;
        }

        try {
            DB::beginTransaction();

            $facturasIds = [];

            // Revertir saldos: sumar de vuelta al movirubro
            foreach ($pago->detalles as $detalle) {
                if ($detalle->movirubro) {
                    $nuevoSaldo = $detalle->movirubro->saldo_rubro + $detalle->valor_pagado;
                    Movirubro::where('id', $detalle->movirubro_id)->update(['saldo_rubro' => $nuevoSaldo]);
                }

                if ($detalle->factura) {
                    $facturasIds[] = $detalle->factura_id;
                }
            }

            // Revertir facturas: pagada → emitida
            if (!empty($facturasIds)) {
                Factura::whereIn('id', $facturasIds)->update(['estado' => 'emitida']);
            }

            // Marcar pago como anulado
            $pago->update(['estado' => 'anulada']);

            // Revertir consecutivo de pagos
            $contrato = $pago->contrato;
            if ($contrato && $contrato->cansecu_pagos > 0) {
                $contrato->update(['cansecu_pagos' => $contrato->cansecu_pagos - 1]);
            }

            DB::commit();

            session()->flash('message', 'Pago ' . $this->pagoToAnularNumero . ' anulado. Saldos revertidos.');
            $this->closeAnularModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error al anular: ' . $e->getMessage());
            $this->closeAnularModal();
        }
    }

    /** Retorna las clases CSS para el badge de estado del pago */
    public function getEstadoColor(string $estado): string
    {
        return match ($estado) {
            'abierto' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'cerrado' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'anulada' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
            default => 'bg-gray-100 text-gray-700',
        };
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado con título y botón "Nuevo Pago" --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Pagos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Listado de pagos registrados.</p>
        </div>
        <a href="{{ route('pagos.crear') }}" class="btn bg-violet-500 hover:bg-violet-600 text-white">
            <svg class="w-4 h-4 fill-current mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
            Nuevo Pago
        </a>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- Búsqueda por número de pago o contrato --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por número o contrato..." class="form-input w-full max-w-xs" />
    </div>

    {{-- Tabla de pagos con acciones por estado --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Número</th>
                    <th class="px-4 py-3 text-left">Contrato</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-right">Valor Total</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Consec. Informe</th>
                    <th class="px-4 py-3 text-center">Trámite</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->pagos as $pago)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $pago->numero }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $pago->contrato->numcontrato ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $pago->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($pago->valor_total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $this->getEstadoColor($pago->estado) }}">
                                {{ ucfirst($pago->estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $pago->cansecu_infor }}</td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $pago->tramitePago->numero ?? '-' }}</td>
                        {{-- Acciones: abierto → Editar+Eliminar, cerrado+último → Ver+Anular, otro → Solo Ver --}}
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($pago->estado === 'abierto')
                                <a href="{{ route('pagos.editar', $pago->id) }}" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                                </a>
                                <button type="button" wire:click="confirmDelete({{ $pago->id }}, '{{ $pago->numero }}')" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                    <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                                </button>
                            @elseif ($pago->estado === 'cerrado' && $this->esUltimoPago($pago))
                                <a href="{{ route('pagos.editar', $pago->id) }}" class="text-gray-400 hover:text-gray-500 mr-3" title="Ver">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                                <button type="button" wire:click="confirmAnular({{ $pago->id }}, '{{ $pago->numero }}')" class="text-amber-500 hover:text-amber-600" title="Anular">
                                    <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                                </button>
                            @else
                                <a href="{{ route('pagos.editar', $pago->id) }}" class="text-gray-400 hover:text-gray-500" title="Ver">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay pagos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->pagos->links() }}</div>

    @if ($confirmModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeConfirmModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[80vh] overflow-y-auto" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Pago</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">
                    ¿Estás seguro de eliminar el pago <span class="font-semibold">{{ $pagoToDeleteNumero }}</span>?
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeConfirmModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="executeDelete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Confirmar Anulación --}}
    @if ($confirmAnularModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeAnularModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[80vh] overflow-y-auto" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Anular Pago</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-2">
                    ¿Estás seguro de anular el pago <span class="font-semibold">{{ $pagoToAnularNumero }}</span>?
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-6">
                    Se revertirán los saldos de los rubros y las facturas volverán a estado <span class="font-semibold">emitida</span>.
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeAnularModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="executeAnular" class="btn bg-amber-500 hover:bg-amber-600 text-white border border-amber-500">Anular</button>
                </div>
            </div>
        </div>
    @endif
</div>
