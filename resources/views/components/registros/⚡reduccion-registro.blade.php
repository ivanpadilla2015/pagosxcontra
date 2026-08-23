<?php

use Livewire\Component;
use App\Models\Registro;
use App\Models\Movirubro;
use App\Models\Contrato;
use App\Models\Tiporegistro;
use App\Models\rubro;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'desc';

    // Modal states
    public $registro_id;
    public $showFormModal = false;
    public $showDeleteModal = false;
    public $confirmDeleteId = null;
    public $editing = false;

    // Registro fields
    public $contrato_id;
    public $numcontrato = '';
    public $contrato_encontrado = false;
    public $resultados_busqueda = [];
    public $numero_reg;
    public $fecha_reg;
    public $newplazoejecucion;
    public $valor_reg = 0;
    public $estado = true;

    // Movirubros individuales del contrato (Primer Registro y Adiciones)
    public $movirubros_existentes = [];
    public $detalles = [];

    // Fields for existing rubro reduction
    public $selected_movirubro_index = null;
    public $reduce_valor_rubro = 0;

    #[Computed]
    public function registros()
    {
        $regionalId = auth()->user()->regional_id;

        return Registro::with(['contrato.proveedor', 'tiporegistro'])
            ->where('tiporegistro_id', 3)
            ->whereHas('contrato.user', function ($q) use ($regionalId) {
                $q->where('regional_id', $regionalId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('contrato', fn ($cq) => $cq->where('numcontrato', 'like', '%' . $this->search . '%'))
                        ->orWhere('numero_reg', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function totalDetalles()
    {
        return collect($this->detalles)->sum('valor_rubro');
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

    public function buscarContrato()
    {
        if (strlen($this->numcontrato) < 2) {
            $this->resultados_busqueda = [];
            $this->contrato_encontrado = false;
            return;
        }

        $regionalId = auth()->user()->regional_id;

        $this->resultados_busqueda = Contrato::with('proveedor')
            ->whereHas('user', function ($q) use ($regionalId) {
                $q->where('regional_id', $regionalId);
            })
            ->where('numcontrato', 'like', '%' . $this->numcontrato . '%')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'numcontrato' => $c->numcontrato,
                'proveedor' => $c->proveedor,
            ])
            ->toArray();
    }

    public function seleccionarContrato($id)
    {
        $contrato = Contrato::with(['proveedor', 'registros.movirubros.rubro', 'registros.tiporegistro'])->find($id);
        if ($contrato) {
            $this->contrato_id = $contrato->id;
            $this->numcontrato = $contrato->numcontrato;
            $this->contrato_encontrado = true;
            $this->resultados_busqueda = [];

            // Load individual movirubros from Primer Registro and Adiciones
            $this->movirubros_existentes = [];
            foreach ($contrato->registros as $registro) {
                if ($registro->tiporegistro_id != 3) { // Skip Reducciones
                    foreach ($registro->movirubros as $movirubro) {
                        if ($movirubro->saldo_rubro > 0) {
                            $this->movirubros_existentes[] = [
                                'movirubro_id' => $movirubro->id,
                                'rubro_id' => $movirubro->rubro_id,
                                'nombre_rubro' => $movirubro->rubro->nombre_rubro ?? '',
                                'codigo_rubro' => $movirubro->rubro->codigo_rubro ?? '',
                                'saldo_rubro' => $movirubro->saldo_rubro,
                                'registro_tipo' => $registro->tiporegistro->name ?? '',
                                'registro_numero' => $registro->numero_reg,
                                'dependencia_afectacion' => $movirubro->dependencia_afectacion,
                            ];
                        }
                    }
                }
            }
        }
    }

    public function addReduccion($index)
    {
        $this->validate([
            'reduce_valor_rubro' => 'required|numeric|min:1',
        ]);

        $movirubro = $this->movirubros_existentes[$index];

        if ($this->reduce_valor_rubro > $movirubro['saldo_rubro']) {
            session()->flash('error', 'El valor a reducir no puede ser mayor al saldo del movirubro.');
            return;
        }

        $this->detalles[] = [
            'movirubro_id' => $movirubro['movirubro_id'],
            'rubro_id' => $movirubro['rubro_id'],
            'nombre_rubro' => $movirubro['nombre_rubro'],
            'codigo_rubro' => $movirubro['codigo_rubro'],
            'valor_rubro' => $this->reduce_valor_rubro,
            'saldo_anterior' => $movirubro['saldo_rubro'],
            'nuevo_saldo' => $movirubro['saldo_rubro'] - $this->reduce_valor_rubro,
            'dependencia_afectacion' => $movirubro['dependencia_afectacion'],
            'registro_tipo' => $movirubro['registro_tipo'],
            'registro_numero' => $movirubro['registro_numero'],
        ];

        $this->selected_movirubro_index = null;
        $this->reduce_valor_rubro = 0;
    }

    public function removeDetalle($index)
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
    }

    public function create()
    {
        $this->resetInputFields();
        $this->editing = false;
        $this->detalles = [];
        $this->movirubros_existentes = [];
        $this->showFormModal = true;
    }

    public function edit($id)
    {
        $registro = Registro::with(['movirubros.rubro', 'movirubros.movirubroPadre.registro.tiporegistro', 'contrato'])->findOrFail($id);
        $this->registro_id = $registro->id;
        $this->contrato_id = $registro->contrato_id;
        $this->numcontrato = $registro->contrato->numcontrato ?? '';
        $this->contrato_encontrado = true;
        $this->numero_reg = $registro->numero_reg;
        $this->fecha_reg = $registro->fecha_reg?->format('Y-m-d');
        $this->newplazoejecucion = $registro->newplazoejecucion?->format('Y-m-d');
        $this->valor_reg = $registro->valor_reg;
        $this->estado = $registro->estado;

        // Load existing movirubros from contract
        $this->seleccionarContrato($registro->contrato_id);

        $this->detalles = $registro->movirubros->map(fn ($m) => [
            'id' => $m->id,
            'movirubro_id' => $m->movirubro_padre_id,
            'rubro_id' => $m->rubro_id,
            'nombre_rubro' => $m->rubro->nombre_rubro ?? '',
            'codigo_rubro' => $m->rubro->codigo_rubro ?? '',
            'valor_rubro' => $m->valor_rubro,
            'saldo_anterior' => $m->movirubroPadre?->saldo_rubro + $m->valor_rubro ?? 0,
            'nuevo_saldo' => $m->movirubroPadre?->saldo_rubro ?? 0,
            'dependencia_afectacion' => $m->dependencia_afectacion,
            'registro_tipo' => $m->movirubroPadre?->registro?->tiporegistro?->name ?? '',
            'registro_numero' => $m->movirubroPadre?->registro?->numero_reg ?? '',
        ])->toArray();

        $this->editing = true;
        $this->showFormModal = true;
    }

    public function store()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'numero_reg' => 'required|string|max:191',
            'fecha_reg' => 'required|date',
        ]);

        if (empty($this->detalles)) {
            session()->flash('error', 'Debe agregar al menos un rubro para reducir.');
            return;
        }

        $registro = Registro::create([
            'numero_reg' => $this->numero_reg,
            'fecha_reg' => $this->fecha_reg,
            'newplazoejecucion' => $this->newplazoejecucion,
            'valor_reg' => $this->totalDetalles,
            'estado' => $this->estado,
            'tiporegistro_id' => 3, // Reduccion
            'contrato_id' => $this->contrato_id,
        ]);

        foreach ($this->detalles as $detalle) {
            // Create reduction movirubro with reference to parent
            Movirubro::create([
                'registro_id' => $registro->id,
                'rubro_id' => $detalle['rubro_id'],
                'valor_rubro' => $detalle['valor_rubro'],
                'saldo_rubro' => $detalle['valor_rubro'],
                'dependencia_afectacion' => $detalle['dependencia_afectacion'],
                'contrato_id' => $this->contrato_id,
                'movirubro_padre_id' => $detalle['movirubro_id'],
            ]);

            // Update target movirubro's saldo
            Movirubro::where('id', $detalle['movirubro_id'])
                ->decrement('saldo_rubro', $detalle['valor_rubro']);
        }

        session()->flash('message', 'Reducción de registro creada exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function update()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'numero_reg' => 'required|string|max:191',
            'fecha_reg' => 'required|date',
        ]);

        if (empty($this->detalles)) {
            session()->flash('error', 'Debe agregar al menos un rubro para reducir.');
            return;
        }

        $registro = Registro::findOrFail($this->registro_id);

        // Restore saldo for old movirubros before updating
        foreach ($registro->movirubros as $oldMovirubro) {
            Movirubro::where('id', $oldMovirubro->movirubro_padre_id)
                ->increment('saldo_rubro', $oldMovirubro->valor_rubro);
        }

        // Delete old movirubros
        $registro->movirubros()->delete();

        $registro->update([
            'numero_reg' => $this->numero_reg,
            'fecha_reg' => $this->fecha_reg,
            'newplazoejecucion' => $this->newplazoejecucion,
            'valor_reg' => $this->totalDetalles,
            'estado' => $this->estado,
            'tiporegistro_id' => 3,
            'contrato_id' => $this->contrato_id,
        ]);

        // Create new movirubros and update target movirubros' saldo
        foreach ($this->detalles as $detalle) {
            Movirubro::create([
                'registro_id' => $registro->id,
                'rubro_id' => $detalle['rubro_id'],
                'valor_rubro' => $detalle['valor_rubro'],
                'saldo_rubro' => $detalle['valor_rubro'],
                'dependencia_afectacion' => $detalle['dependencia_afectacion'],
                'contrato_id' => $this->contrato_id,
                'movirubro_padre_id' => $detalle['movirubro_id'],
            ]);

            // Update target movirubro's saldo
            Movirubro::where('id', $detalle['movirubro_id'])
                ->decrement('saldo_rubro', $detalle['valor_rubro']);
        }

        session()->flash('message', 'Reducción de registro actualizada exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function confirmDelete($id)
    {
        $this->confirmDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        $registro = Registro::findOrFail($this->confirmDeleteId);

        // Restore saldo for target movirubros before deleting
        foreach ($registro->movirubros as $movirubro) {
            Movirubro::where('id', $movirubro->movirubro_padre_id)
                ->increment('saldo_rubro', $movirubro->valor_rubro);
        }

        // Delete movirubros
        $registro->movirubros()->delete();
        $registro->delete();

        session()->flash('message', 'Reducción de registro eliminada exitosamente. Los saldos han sido restaurados.');
        $this->confirmDeleteId = null;
        $this->showDeleteModal = false;
    }

    public function closeModal()
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function updatedShowFormModal($value)
    {
        if (! $value) {
            $this->resetInputFields();
        }
    }

    private function resetInputFields(): void
    {
        $this->reset();
        $this->numcontrato = '';
        $this->contrato_encontrado = false;
        $this->resultados_busqueda = [];
        $this->detalles = [];
        $this->movirubros_existentes = [];
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Reducción de Registros</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de reducciones a contratos</p>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="w-full sm:max-w-xs">
                    <x-input type="text" wire:model.live="search" placeholder="Buscar por contrato o número..." />
                </div>
                <x-button wire:click="create">
                    <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 16 16">
                        <path d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                    </svg>
                    <span class="ml-2">Crear Reducción</span>
                </x-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th wire:click="sortBy('id')" class="cursor-pointer px-6 py-4 text-left">
                                ID
                                @if ($sortField === 'id')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-left">N° Registro</th>
                            <th class="px-6 py-4 text-left">Contrato</th>
                            <th class="px-6 py-4 text-left">Fecha</th>
                            <th class="px-6 py-4 text-left">Valor</th>
                            <th class="px-6 py-4 text-center">Estado</th>
                            <th class="px-6 py-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->registros as $registro)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">{{ $registro->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">
                                    {{ $registro->numero_reg }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $registro->contrato->numcontrato ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $registro->fecha_reg?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${{ number_format($registro->valor_reg, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($registro->estado)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button wire:click="edit({{ $registro->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 transition" title="Editar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        <button wire:click="confirmDelete({{ $registro->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Eliminar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron reducciones de registros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->registros->links() }}
            </div>
        </div>

        {{-- Modal Formulario --}}
        <div wire:ignore.self wire:key="form-modal"
            x-data="{ show: @entangle('showFormModal') }"
            x-show="show"
            @keydown.escape.window="show = false"
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500 opacity-75 dark:bg-gray-900"
                x-show="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            </div>
            <div x-show="show"
                class="mb-6 w-full max-w-6xl mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="px-6 py-4 max-h-[85vh] overflow-y-auto">
                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editing ? 'Editar Reducción' : 'Crear Nueva Reducción' }}
                    </div>

                    {{-- Selección de Contrato --}}
                    <div class="mb-6" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contrato <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" wire:model.live="numcontrato" 
                                wire:keydown.debounce.300ms="buscarContrato"
                                @focus="open = true"
                                @keydown.escape="open = false"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Digite el número del contrato..."
                                {{ $editing ? 'disabled' : '' }}
                                autocomplete="off" />
                            
                            @if ($contrato_encontrado)
                                <div class="mt-1 flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Contrato encontrado
                                </div>
                            @endif

                            @if (count($resultados_busqueda) > 0 && !$contrato_encontrado && !$editing)
                                <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                    @foreach ($resultados_busqueda as $resultado)
                                        <button type="button" 
                                            wire:click="seleccionarContrato({{ $resultado['id'] }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-left hover:bg-violet-50 dark:hover:bg-violet-900/20 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $resultado['numcontrato'] }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $resultado['proveedor']['nombre'] ?? '-' }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <input type="hidden" wire:model="contrato_id" />
                        <x-input-error for="contrato_id" />
                        <x-input-error for="numcontrato" />
                    </div>

                    <div class="space-y-6">
                        {{-- Sección: Datos de la Reducción --}}
                        <div class="border-l-4 border-red-500 bg-red-50/50 dark:bg-red-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3">Datos de la Reducción</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-label for="numero_reg" value="N° Registro *" />
                                    <x-input id="numero_reg" type="text" wire:model="numero_reg" class="w-full" />
                                    <x-input-error for="numero_reg" />
                                </div>
                                <div>
                                    <x-label for="fecha_reg" value="Fecha Registro *" />
                                    <x-input id="fecha_reg" type="date" wire:model="fecha_reg" class="w-full" />
                                    <x-input-error for="fecha_reg" />
                                </div>
                                <div>
                                    <x-label for="newplazoejecucion" value="Nuevo Plazo Ejecución" />
                                    <x-input id="newplazoejecucion" type="date" wire:model="newplazoejecucion" class="w-full" />
                                </div>
                                <div class="flex items-center gap-2 pt-6">
                                    <input type="checkbox" wire:model="estado" id="estado" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label for="estado" class="text-sm text-gray-700 dark:text-gray-300">Estado Activo</label>
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Movirubros Existentes del Contrato --}}
                        @if (count($movirubros_existentes) > 0)
                            <div class="border-l-4 border-amber-500 bg-amber-50/50 dark:bg-amber-900/10 rounded-r-lg p-4">
                                <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-3">Movirubros del Contrato (Primer Registro y Adiciones)</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                <th class="px-4 py-2 text-left">Tipo Registro</th>
                                                <th class="px-4 py-2 text-left">N° Registro</th>
                                                <th class="px-4 py-2 text-left">Código</th>
                                                <th class="px-4 py-2 text-left">Nombre</th>
                                                <th class="px-4 py-2 text-right">Saldo Disponible</th>
                                                <th class="px-4 py-2 text-left">Dependencia</th>
                                                <th class="px-4 py-2 text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach ($movirubros_existentes as $i => $movirubro)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                            @if($movirubro['registro_tipo'] === 'Primer Registro') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                            @else bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                                            @endif">
                                                            {{ $movirubro['registro_tipo'] }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">{{ $movirubro['registro_numero'] }}</td>
                                                    <td class="px-4 py-2">{{ $movirubro['codigo_rubro'] }}</td>
                                                    <td class="px-4 py-2 font-medium">{{ $movirubro['nombre_rubro'] }}</td>
                                                    <td class="px-4 py-2 text-right">${{ number_format($movirubro['saldo_rubro'], 2, ',', '.') }}</td>
                                                    <td class="px-4 py-2">{{ $movirubro['dependencia_afectacion'] }}</td>
                                                    <td class="px-4 py-2 text-center">
                                                        @if ($selected_movirubro_index === $i)
                                                            <div class="flex items-center gap-2 justify-center">
                                                                <input type="number" wire:model.live="reduce_valor_rubro" min="1" max="{{ $movirubro['saldo_rubro'] }}" class="w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm" placeholder="Valor a reducir">
                                                                <button wire:click="addReduccion({{ $i }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Confirmar reducción">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                </button>
                                                                <button wire:click="$set('selected_movirubro_index', null); $set('reduce_valor_rubro', 0)" class="text-gray-400 hover:text-gray-600 transition" title="Cancelar">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <button wire:click="$set('selected_movirubro_index', {{ $i }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Reducir este movirubro">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                                                </svg>
                                                                Reducir
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Sección: Detalle de Reducciones --}}
                        <div class="border-l-4 border-violet-500 bg-violet-50/50 dark:bg-violet-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-400 mb-3">Detalle de Reducción</h3>

                            @if (count($detalles) > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                <th class="px-4 py-2 text-left">#</th>
                                                <th class="px-4 py-2 text-left">Tipo Registro</th>
                                                <th class="px-4 py-2 text-left">N° Registro</th>
                                                <th class="px-4 py-2 text-left">Código</th>
                                                <th class="px-4 py-2 text-left">Nombre Rubro</th>
                                                <th class="px-4 py-2 text-right">Valor Reducción</th>
                                                <th class="px-4 py-2 text-left">Dependencia</th>
                                                <th class="px-4 py-2 text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach ($detalles as $i => $detalle)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td class="px-4 py-2">{{ $i + 1 }}</td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                            @if($detalle['registro_tipo'] === 'Primer Registro') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                            @else bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                                            @endif">
                                                            {{ $detalle['registro_tipo'] }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">{{ $detalle['registro_numero'] }}</td>
                                                    <td class="px-4 py-2">{{ $detalle['codigo_rubro'] }}</td>
                                                    <td class="px-4 py-2 font-medium">{{ $detalle['nombre_rubro'] }}</td>
                                                    <td class="px-4 py-2 text-right text-red-600 dark:text-red-400">-${{ number_format($detalle['valor_rubro'], 2, ',', '.') }}</td>
                                                    <td class="px-4 py-2">{{ $detalle['dependencia_afectacion'] }}</td>
                                                    <td class="px-4 py-2 text-center">
                                                        <button wire:click="removeDetalle({{ $i }})" class="text-red-600 hover:text-red-800 transition" title="Eliminar">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="font-semibold text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700/50">
                                                <td colspan="5" class="px-4 py-2 text-right">TOTALES:</td>
                                                <td class="px-4 py-2 text-right text-red-600 dark:text-red-400">-${{ number_format($this->totalDetalles, 2, ',', '.') }}</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                                    No hay rubros seleccionados para reducir. Use la tabla superior para seleccionar rubros.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <x-secondary-button wire:click="closeModal">
                            Cancelar
                        </x-secondary-button>
                        <x-button wire:click="{{ $editing ? 'update' : 'store' }}">
                            {{ $editing ? 'Actualizar' : 'Guardar' }}
                        </x-button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Confirmar Eliminación --}}
        <div wire:ignore.self wire:key="delete-modal"
            x-data="{ show: @entangle('showDeleteModal') }"
            x-show="show"
            @keydown.escape.window="show = false"
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500 opacity-75 dark:bg-gray-900"
                x-show="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            </div>
            <div x-show="show"
                class="mb-6 w-full max-w-md mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/30 rounded-full">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Confirmar Eliminación</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">¿Está seguro que desea eliminar esta reducción? Esta acción no se puede deshacer.</p>
                        <div class="flex justify-center gap-3">
                            <x-secondary-button wire:click="closeModal">
                                Cancelar
                            </x-secondary-button>
                            <x-danger-button wire:click="delete">
                                Eliminar
                            </x-danger-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
