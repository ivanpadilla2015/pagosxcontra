<?php

use Livewire\Component;
use App\Models\Contrato;
use App\Models\Itemcontrato;
use App\Models\Producto;
use App\Models\Movirubro;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $numcontrato = '';
    public $contrato = null;
    public $contratoError = null;

    public $filter_rubro_id = null;

    // Formulario de asignación
    public $selected_producto_id = null;
    public $selected_movirubro_id = null;
    public $producto_search = '';

    public $valor_costo = 0;
    public $iva = 19.0;
    public $valor_iva = 0;
    public $valor_con_iva = 0;

    public $editingId = null;

    // Modal eliminar
    public $deleteModalOpen = false;
    public $itemToDeleteId = null;
    public $itemToDeleteName = '';

    public $search = '';

    protected function rules(): array
    {
        return [
            'selected_producto_id' => 'required|exists:productos,id',
            'selected_movirubro_id' => 'required|exists:movirubros,id',
            'valor_costo' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0|max:100',
            'valor_iva' => 'required|numeric|min:0',
            'valor_con_iva' => 'required|numeric|min:0',
        ];
    }

    #[Computed]
    public function productosFiltrados()
    {
        if (strlen($this->producto_search) < 2) {
            return collect();
        }

        $query = Producto::with(['uso', 'rubro'])
            ->where('name', 'like', '%' . $this->producto_search . '%')
            ->where('regional_id', Auth::user()->regional_id);

        if ($this->filter_rubro_id) {
            $query->where('rubro_id', $this->filter_rubro_id);
        }

        return $query->limit(15)->get();
    }

    #[Computed]
    public function rubrosDisponibles()
    {
        if (!$this->contrato) {
            return collect();
        }

        $movirubros = Movirubro::where('contrato_id', $this->contrato->id)
            ->whereHas('registro', function ($q) {
                $q->where('tiporegistro_id', '!=', 3);
            })
            ->with(['rubro', 'registro'])
            ->get()
            ->groupBy('rubro_id')
            ->map(function ($group, $rubroId) {
                $primerMovirubro = $group->first();
                return [
                    'rubro_id' => $rubroId,
                    'rubro_codigo' => $primerMovirubro->rubro->codigo_rubro ?? '-',
                    'rubro_nombre' => $primerMovirubro->rubro->nombre_rubro ?? '-',
                    'saldo_total' => $group->sum('saldo_rubro'),
                    'valor_total' => $group->sum('valor_rubro'),
                    'movirubros' => $group,
                ];
            })
            ->values();

        return $movirubros->filter(fn ($rubro) => $rubro['saldo_total'] > 0)->values();
    }

    #[Computed]
    public function items()
    {
        if (!$this->contrato) {
            return collect();
        }

        return Itemcontrato::query()
            ->with(['producto.rubro', 'producto.uso', 'movirubro.rubro', 'movirubro.registro'])
            ->where('contrato_id', $this->contrato->id)
            ->when($this->search, function ($q) {
                $q->whereHas('producto', fn ($pq) => $pq->where('name', 'like', '%' . $this->search . '%'));
            })
            ->latest()
            ->paginate(15);
    }

    public function buscarContrato(): void
    {
        $this->reset(['contrato', 'contratoError', 'filter_rubro_id']);
        $this->resetForm();
        $this->resetValidation();

        $numero = trim($this->numcontrato);
        if ($numero === '') {
            $this->contratoError = 'Ingrese el número del contrato.';
            return;
        }

        $contrato = Contrato::with(['proveedor'])->where('numcontrato', $numero)->first();
        if (!$contrato) {
            $this->contratoError = 'No se encontró un contrato con el número ' . $numero . '.';
            return;
        }

        $tieneRegistros = Movirubro::where('contrato_id', $contrato->id)->exists();
        if (!$tieneRegistros) {
            $this->contratoError = 'Este contrato no tiene registros presupuestales asignados. Primero debe asignarlos en la sección de Registros antes de poder asignar productos.';
            return;
        }

        $this->contrato = $contrato;
    }

    public function seleccionarProducto($id): void
    {
        $producto = Producto::with(['uso', 'rubro'])->find($id);
        if ($producto) {
            $this->selected_producto_id = $producto->id;
            $this->producto_search = $producto->name . ' (' . ($producto->uso->codigo_uso ?? '-') . ')';
        }
    }

    public function save(): void
    {
        if (!$this->contrato) {
            $this->contratoError = 'Debe buscar y seleccionar un contrato válido.';
            return;
        }

        $data = $this->validate();

        $producto = Producto::with(['rubro'])->find($this->selected_producto_id);

        $data['contrato_id'] = $this->contrato->id;
        $data['producto_id'] = $this->selected_producto_id;
        $data['rubro_id'] = $producto->rubro_id;
        $data['movirubro_id'] = $this->selected_movirubro_id;
        $data['iva'] = $this->iva;

        $existe = Itemcontrato::where('contrato_id', $this->contrato->id)
            ->where('rubro_id', $producto->rubro_id)
            ->where('producto_id', $this->selected_producto_id)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($existe) {
            session()->flash('error', 'Este producto ya está asignado a este rubro en el contrato.');
            $this->js('window.scrollTo({top: 0, behavior: "smooth"})');
            return;
        }

        if ($this->editingId) {
            Itemcontrato::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Producto asignado actualizado correctamente.');
        } else {
            Itemcontrato::create($data);
            session()->flash('message', 'Producto asignado al contrato correctamente.');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $item = Itemcontrato::with(['producto.uso', 'movirubro.rubro', 'movirubro.registro'])->findOrFail($id);
        $this->editingId = $item->id;
        $this->selected_producto_id = $item->producto_id;
        $this->producto_search = $item->producto->name . ' (' . ($item->producto->uso->codigo_uso ?? '-') . ')';
        $this->selected_movirubro_id = $item->movirubro_id;
        $this->filter_rubro_id = $item->rubro_id;
        $this->valor_costo = $item->valor_costo;
        $this->iva = $item->iva ?? 19.0;
        $this->valor_iva = $item->valor_iva;
        $this->valor_con_iva = $item->valor_con_iva;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['selected_producto_id', 'producto_search', 'valor_costo', 'iva', 'valor_iva', 'valor_con_iva', 'editingId']);
        $this->iva = 19.0;
        $this->resetValidation();
    }

    public function confirmDelete(int $id, string $name): void
    {
        $this->itemToDeleteId = $id;
        $this->itemToDeleteName = $name;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->itemToDeleteId = null;
        $this->itemToDeleteName = '';
    }

    public function delete(): void
    {
        Itemcontrato::findOrFail($this->itemToDeleteId)->delete();
        session()->flash('message', 'Producto eliminado del contrato correctamente.');
        $this->closeDeleteModal();
    }

    public function updatedValorCosto(): void
    {
        $this->valor_iva = round(floatval($this->valor_costo) * floatval($this->iva) / 100, 2);
        $this->valor_con_iva = round(floatval($this->valor_costo) + floatval($this->valor_iva), 2);
    }

    public function updatedIva(): void
    {
        $this->valor_iva = round(floatval($this->valor_costo) * floatval($this->iva) / 100, 2);
        $this->valor_con_iva = round(floatval($this->valor_costo) + floatval($this->valor_iva), 2);
    }

    public function updatedValorIva(): void
    {
        $this->valor_con_iva = round(floatval($this->valor_costo) + floatval($this->valor_iva), 2);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function filterByRubro($rubroId): void
    {
        if ($this->filter_rubro_id === $rubroId) {
            return;
        }

        $this->filter_rubro_id = $rubroId;
        $this->selected_producto_id = null;
        $this->producto_search = '';
        $this->editingId = null;
        $this->valor_costo = 0;
        $this->valor_iva = 0;
        $this->valor_con_iva = 0;

        $movirubro = Movirubro::where('contrato_id', $this->contrato->id)
            ->where('rubro_id', $rubroId)
            ->whereHas('registro', function ($q) {
                $q->where('tiporegistro_id', '!=', 3);
            })
            ->orderBy('saldo_rubro', 'desc')
            ->first();

        $this->selected_movirubro_id = $movirubro?->id;
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Asignar Productos al Contrato</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asigne productos a los rubros presupuestales de un contrato.</p>
        </div>

        @if (session('message'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed top-4 right-4 z-50 max-w-sm rounded-lg bg-emerald-50 border border-emerald-300 px-4 py-3 shadow-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span class="text-sm font-medium text-emerald-700">{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed top-4 right-4 z-50 max-w-sm rounded-lg bg-rose-50 border border-rose-300 px-4 py-3 shadow-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <span class="text-sm font-medium text-rose-700">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{-- Paso 1: Buscar contrato --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">1. Buscar Contrato</h2>
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de contrato</label>
                    <input type="text" wire:model="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: CT-2026-001" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 @error('numcontrato') border-rose-500 @endif" />
                </div>
                <button type="button" wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                    Buscar
                </button>
            </div>
            @if ($contratoError)
                <p class="mt-2 text-sm text-rose-500">{{ $contratoError }}</p>
            @endif
            @if ($contrato)
                <div class="mt-4 rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700/60 px-4 py-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Contrato: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Proveedor: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '-' }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Productos asignados: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ \App\Models\Itemcontrato::where('contrato_id', $contrato->id)->count() }}</span></p>
                </div>
            @endif
        </div>

        @if ($contrato)
            {{-- Paso 2: Rubros presupuestales del contrato --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">2. Seleccionar Rubro Presupuestal</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Haga clic en un rubro. El sistema asignará automáticamente el movirubro con mayor saldo de ese rubro.</p>

                @if (count($this->rubrosDisponibles) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left">Código</th>
                                    <th class="px-4 py-3 text-left">Nombre Rubro</th>
                                    <th class="px-4 py-3 text-right">Valor Total</th>
                                    <th class="px-4 py-3 text-right">Saldo Disponible</th>
                                    <th class="px-4 py-3 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($this->rubrosDisponibles as $rubro)
                                    <tr class="{{ $filter_rubro_id === $rubro['rubro_id'] ? 'bg-violet-50 dark:bg-violet-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $rubro['rubro_codigo'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $rubro['rubro_nombre'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($rubro['valor_total'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $rubro['saldo_total'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            ${{ number_format($rubro['saldo_total'], 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button wire:click="filterByRubro({{ $rubro['rubro_id'] }})" class="px-3 py-1 text-xs font-medium rounded-lg transition {{ $filter_rubro_id === $rubro['rubro_id'] ? 'bg-violet-600 text-white' : 'bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-400' }}">
                                                {{ $filter_rubro_id === $rubro['rubro_id'] ? '✓ Seleccionado' : 'Seleccionar' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Este contrato no tiene rubros presupuestales registrados.</p>
                @endif
            </div>

            {{-- Paso 3: Formulario de asignación --}}
            @if ($filter_rubro_id)
                    @php
                        $rubroInfo = $this->rubrosDisponibles->firstWhere('rubro_id', $filter_rubro_id);
                        $movInfo = $rubroInfo ? $rubroInfo['movirubros']->firstWhere('id', $selected_movirubro_id) : null;
                    @endphp

                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar Asignación' : '3. Asignar Producto' }}</h2>

                    {{-- Info del rubro/movirubro seleccionado --}}
                    <div class="mb-5 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/60">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="font-medium text-blue-800 dark:text-blue-300">
                                Rubro: {{ $rubroInfo['rubro_codigo'] }} - {{ $rubroInfo['rubro_nombre'] }}
                            </span>
                            <span class="text-blue-600 dark:text-blue-400">
                                | Saldo: ${{ number_format($rubroInfo['saldo_total'], 2, ',', '.') }}
                            </span>
                            @if ($movInfo)
                                <span class="text-blue-600 dark:text-blue-400">
                                    | Asignado a: Reg. N° {{ $movInfo->registro->numero_reg ?? '-' }}
                                    (Saldo: ${{ number_format($movInfo->saldo_rubro, 2, ',', '.') }})
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Selección de producto --}}
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Buscar y seleccionar producto *</label>
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <input type="text" wire:model.live="producto_search" @focus="open = true" @keydown.escape="open = false" placeholder="Escriba para buscar productos de este rubro..." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" autocomplete="off" />
                            @if ($selected_producto_id)
                                <button type="button" wire:click="$set('selected_producto_id', null); $set('producto_search', '')" class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                            @if (strlen($producto_search) >= 2 && !$selected_producto_id)
                                <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    @forelse ($this->productosFiltrados as $p)
                                        <button type="button" wire:click="seleccionarProducto({{ $p->id }})" class="w-full text-left px-3 py-2 hover:bg-violet-50 dark:hover:bg-gray-600 text-sm border-b border-gray-100 dark:border-gray-600 last:border-0">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $p->name }}</span>
                                            <span class="text-gray-500 dark:text-gray-400 ml-1">({{ $p->uso->codigo_uso ?? '-' }})</span>
                                        </button>
                                    @empty
                                        <div class="px-3 py-2 text-sm text-gray-500">No se encontraron productos para este rubro.</div>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                        @error('selected_producto_id') <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    {{-- Valores --}}
                    @if ($selected_producto_id)
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Digite los valores</label>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Costo *</label>
                                    <input type="number" step="0.01" wire:model.live="valor_costo" placeholder="0.00" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 @error('valor_costo') border-rose-500 @endif" />
                                    @error('valor_costo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IVA % *</label>
                                    <input type="number" step="0.01" wire:model.live="iva" placeholder="19" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 @error('iva') border-rose-500 @endif" />
                                    @error('iva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor IVA</label>
                                    <input type="number" step="0.01" wire:model.live="valor_iva" placeholder="0.00" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 bg-gray-50 dark:bg-gray-600" readonly />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor con IVA *</label>
                                    <input type="number" step="0.01" wire:model="valor_con_iva" placeholder="0.00" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 bg-gray-50 dark:bg-gray-600 @error('valor_con_iva') border-rose-500 @endif" readonly />
                                    @error('valor_con_iva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            @if ($editingId)
                                <button type="button" wire:click="resetForm" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                    Cancelar
                                </button>
                            @endif
                            <button type="button" wire:click="save" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition">
                                {{ $editingId ? 'Actualizar' : 'Asignar Producto' }}
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Listado de productos asignados --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Productos Asignados</h2>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por producto..." class="w-full sm:max-w-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 text-sm" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left">Producto</th>
                                <th class="px-4 py-3 text-left">Código Uso</th>
                                <th class="px-4 py-3 text-left">Rubro</th>
                                <th class="px-4 py-3 text-left">Movirubro</th>
                                <th class="px-4 py-3 text-right">Valor Costo</th>
                                <th class="px-4 py-3 text-center">IVA %</th>
                                <th class="px-4 py-3 text-right">Valor IVA</th>
                                <th class="px-4 py-3 text-right">Valor c/IVA</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($this->items as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->producto->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->producto->uso->codigo_uso ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->producto->rubro->nombre_rubro ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item->movirubro->rubro->codigo_rubro ?? '-' }} - Reg: {{ $item->movirubro->registro->numero_reg ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($item->valor_costo, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $item->iva ?? '-' }}%</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->valor_iva, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($item->valor_con_iva, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <button wire:click="edit({{ $item->id }})" class="text-violet-500 hover:text-violet-700 mr-2" title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $item->id }}, '{{ $item->producto->name ?? 'Producto' }}')" class="text-rose-500 hover:text-rose-700" title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No hay productos asignados a este contrato.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $this->items->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Modal confirmar eliminación --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Producto</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Está seguro de eliminar el producto <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $itemToDeleteName }}</span> de este contrato?</p>
                <div class="flex justify-center gap-3">
                    <button wire:click="closeDeleteModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Cancelar
                    </button>
                    <button wire:click="delete" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
