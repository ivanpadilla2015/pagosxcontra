<?php

use Livewire\Component;
use App\Models\Registro;
use App\Models\Movirubro;
use App\Models\Contrato;
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

    // Rubro search
    public $rubro_search = '';
    public $showRubroDropdown = false;

    // Movimiento/Rubro fields (temporary for adding to detail)
    public $new_rubro_id;
    public $new_valor_rubro = 0;
    public $new_saldo_rubro = 0;
    public $new_dependencia_afectacion;

    // Detail items stored in session-like array
    public $detalles = [];

    // Track if contract already has "Primer Registro"
    public $contrato_tiene_primer_registro = false;

    #[Computed]
    public function registros()
    {
        $regionalId = auth()->user()->regional_id;

        return Registro::with(['contrato.proveedor', 'tiporegistro'])
            ->whereHas('contrato.user', function ($q) use ($regionalId) {
                $q->where('regional_id', $regionalId);
            })
            ->where(function ($q) {
                $q->whereHas('contrato', fn ($cq) => $cq->where('numcontrato', 'like', '%' . $this->search . '%'))
                    ->orWhere('numero_reg', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function contratos()
    {
        return Contrato::with(['proveedor'])->orderBy('numcontrato')->get();
    }

    public function buscarContrato()
    {
        if (strlen($this->numcontrato) < 2) {
            $this->resultados_busqueda = [];
            $this->contrato_encontrado = false;
            return;
        }

        $regionalId = auth()->user()->regional_id;

        $this->resultados_busqueda = Contrato::with(['proveedor'])
            ->whereHas('user', function ($q) use ($regionalId) {
                $q->where('regional_id', $regionalId);
            })
            ->where('numcontrato', 'like', '%' . $this->numcontrato . '%')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function seleccionarContrato($contratoId)
    {
        $contrato = Contrato::with(['proveedor'])->find($contratoId);
        if ($contrato) {
            $this->contrato_id = $contrato->id;
            $this->numcontrato = $contrato->numcontrato;
            $this->contrato_encontrado = true;
            $this->resultados_busqueda = [];

            // Check if contract already has "Primer Registro" (tiporegistro_id = 1)
            $this->contrato_tiene_primer_registro = Registro::where('contrato_id', $contrato->id)
                ->where('tiporegistro_id', 1)
                ->exists();
        }
    }

    #[Computed]
    public function rubrosFiltrados()
    {
        if (strlen($this->rubro_search) < 2) {
            return collect();
        }

        return rubro::where('nombre_rubro', 'like', '%' . $this->rubro_search . '%')
            ->orWhere('codigo_rubro', 'like', '%' . $this->rubro_search . '%')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function totalDetalles()
    {
        return collect($this->detalles)->sum('valor_rubro');
    }

    #[Computed]
    public function totalSaldo()
    {
        return collect($this->detalles)->sum('saldo_rubro');
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

    public function create()
    {
        $this->resetInputFields();
        $this->editing = false;
        $this->detalles = [];
        $this->contrato_tiene_primer_registro = false;
        $this->showFormModal = true;
    }

    public function updatedRubroSearch()
    {
        $this->showRubroDropdown = strlen($this->rubro_search) >= 2;
        $this->new_rubro_id = null;
    }

    public function selectRubro($id)
    {
        $rubro = rubro::find($id);
        if ($rubro) {
            $this->new_rubro_id = $rubro->id;
            $this->rubro_search = $rubro->codigo_rubro . ' - ' . $rubro->nombre_rubro;
            $this->showRubroDropdown = false;
        }
    }

    public function addDetalle()
    {
        $this->validate([
            'new_rubro_id' => 'required|exists:rubros,id',
            'new_valor_rubro' => 'required|numeric|min:0',
        ]);

        $rubro = rubro::find($this->new_rubro_id);

        $this->detalles[] = [
            'rubro_id' => $this->new_rubro_id,
            'nombre_rubro' => $rubro->nombre_rubro,
            'codigo_rubro' => $rubro->codigo_rubro,
            'valor_rubro' => $this->new_valor_rubro,
            'saldo_rubro' => $this->new_saldo_rubro,
            'dependencia_afectacion' => $this->new_dependencia_afectacion,
        ];

        $this->reset(['new_rubro_id', 'new_valor_rubro', 'new_saldo_rubro', 'new_dependencia_afectacion', 'rubro_search']);
    }

    public function removeDetalle($index)
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
    }

    public function store()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'numero_reg' => 'required|string|max:191',
            'fecha_reg' => 'required|date',
        ]);

        // Validate that "Primer Registro" is unique per contract
        $existePrimerRegistro = Registro::where('contrato_id', $this->contrato_id)
            ->where('tiporegistro_id', 1)
            ->exists();

        if ($existePrimerRegistro) {
            session()->flash('error', 'Este contrato ya tiene un "Primer Registro" creado. No se puede crear otro.');
            return;
        }

        if (empty($this->detalles)) {
            session()->flash('error', 'Debe agregar al menos un detalle de rubro.');
            return;
        }

        $registro = Registro::create([
            'numero_reg' => $this->numero_reg,
            'fecha_reg' => $this->fecha_reg,
            'newplazoejecucion' => $this->newplazoejecucion,
            'valor_reg' => $this->totalDetalles,
            'estado' => $this->estado,
            'tiporegistro_id' => 1, // Primer Registro
            'contrato_id' => $this->contrato_id,
        ]);

        foreach ($this->detalles as $detalle) {
            Movirubro::create([
                'registro_id' => $registro->id,
                'rubro_id' => $detalle['rubro_id'],
                'valor_rubro' => $detalle['valor_rubro'],
                'saldo_rubro' => $detalle['saldo_rubro'],
                'dependencia_afectacion' => $detalle['dependencia_afectacion'],
                'contrato_id' => $this->contrato_id,
            ]);
        }

        session()->flash('message', 'Registro creado exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function edit($id)
    {
        $registro = Registro::with(['movirubros.rubro', 'contrato'])->findOrFail($id);
        $this->registro_id = $registro->id;
        $this->contrato_id = $registro->contrato_id;
        $this->numcontrato = $registro->contrato->numcontrato ?? '';
        $this->contrato_encontrado = true;
        $this->numero_reg = $registro->numero_reg;
        $this->fecha_reg = $registro->fecha_reg?->format('Y-m-d');
        $this->newplazoejecucion = $registro->newplazoejecucion?->format('Y-m-d');
        $this->valor_reg = $registro->valor_reg;
        $this->estado = $registro->estado;

        // Check if contract has other "Primer Registro" (excluding current one)
        $this->contrato_tiene_primer_registro = Registro::where('contrato_id', $registro->contrato_id)
            ->where('tiporegistro_id', 1)
            ->where('id', '!=', $id)
            ->exists();

        $this->detalles = $registro->movirubros->map(fn ($m) => [
            'id' => $m->id,
            'rubro_id' => $m->rubro_id,
            'nombre_rubro' => $m->rubro->nombre_rubro ?? '',
            'codigo_rubro' => $m->rubro->codigo_rubro ?? '',
            'valor_rubro' => $m->valor_rubro,
            'saldo_rubro' => $m->saldo_rubro,
            'dependencia_afectacion' => $m->dependencia_afectacion,
        ])->toArray();

        $this->editing = true;
        $this->showFormModal = true;
    }

    public function update()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'numero_reg' => 'required|string|max:191',
            'fecha_reg' => 'required|date',
        ]);

        // Validate that "Primer Registro" is unique per contract (excluding current)
        $existePrimerRegistro = Registro::where('contrato_id', $this->contrato_id)
            ->where('tiporegistro_id', 1)
            ->where('id', '!=', $this->registro_id)
            ->exists();

        if ($existePrimerRegistro) {
            session()->flash('error', 'Este contrato ya tiene un "Primer Registro" creado. No se puede crear otro.');
            return;
        }

        if (empty($this->detalles)) {
            session()->flash('error', 'Debe agregar al menos un detalle de rubro.');
            return;
        }

        $registro = Registro::findOrFail($this->registro_id);
        $registro->update([
            'numero_reg' => $this->numero_reg,
            'fecha_reg' => $this->fecha_reg,
            'newplazoejecucion' => $this->newplazoejecucion,
            'valor_reg' => $this->totalDetalles,
            'estado' => $this->estado,
            'tiporegistro_id' => 1, // Primer Registro
            'contrato_id' => $this->contrato_id,
        ]);

        // Delete old movirubros and recreate
        $registro->movirubros()->delete();
        foreach ($this->detalles as $detalle) {
            Movirubro::create([
                'registro_id' => $registro->id,
                'rubro_id' => $detalle['rubro_id'],
                'valor_rubro' => $detalle['valor_rubro'],
                'saldo_rubro' => $detalle['saldo_rubro'],
                'dependencia_afectacion' => $detalle['dependencia_afectacion'],
                'contrato_id' => $this->contrato_id,
            ]);
        }

        session()->flash('message', 'Registro actualizado exitosamente.');
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
        $registro->movirubros()->delete();
        $registro->delete();

        session()->flash('message', 'Registro eliminado exitosamente.');
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
            $this->editing = false;
        }
    }

    public function updatedShowDeleteModal($value)
    {
        if (! $value) {
            $this->confirmDeleteId = null;
        }
    }

    private function resetInputFields(): void
    {
        $this->reset();
        $this->numcontrato = '';
        $this->contrato_encontrado = false;
        $this->resultados_busqueda = [];
        $this->detalles = [];
        $this->contrato_tiene_primer_registro = false;
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Registros</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de registros de contratos</p>
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
                    <span class="ml-2">Crear Nuevo Registro</span>
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
                            <th class="px-6 py-4 text-left">Tipo</th>
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($registro->tiporegistro_id == 1) bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                        @elseif($registro->tiporegistro_id == 2) bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                        @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                        @endif">
                                        {{ $registro->tiporegistro->name ?? '-' }}
                                    </span>
                                </td>
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
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron registros.
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
                        {{ $editing ? 'Editar Registro' : 'Crear Nuevo Registro' }}
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
                        {{-- Alert when contract already has "Primer Registro" --}}
                        @if ($contrato_tiene_primer_registro && !$editing)
                            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 dark:bg-amber-900/20 dark:border-amber-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Este contrato ya tiene un "Primer Registro" creado</h3>
                                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">No se puede crear otro primer registro para este contrato. Use los módulos de Adición o Reducción para realizar movimientos adicionales.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Sección: Datos del Registro --}}
                        <div class="border-l-4 border-violet-500 bg-violet-50/50 dark:bg-violet-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-400 mb-3">Datos del Registro</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-label for="numero_reg" value="N° Registro *" />
                                    <x-input id="numero_reg" type="text" wire:model="numero_reg" class="w-full" :disabled="$contrato_tiene_primer_registro && !$editing" />
                                    <x-input-error for="numero_reg" />
                                </div>
                                <div>
                                    <x-label for="fecha_reg" value="Fecha Registro *" />
                                    <x-input id="fecha_reg" type="date" wire:model="fecha_reg" class="w-full" :disabled="$contrato_tiene_primer_registro && !$editing" />
                                    <x-input-error for="fecha_reg" />
                                </div>
                                <div>
                                    <x-label for="newplazoejecucion" value="Nuevo Plazo Ejecución" />
                                    <x-input id="newplazoejecucion" type="date" wire:model="newplazoejecucion" class="w-full" :disabled="$contrato_tiene_primer_registro && !$editing" />
                                </div>
                                <div class="flex items-center gap-2 pt-6">
                                    <input type="checkbox" wire:model="estado" id="estado" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500" :disabled="$contrato_tiene_primer_registro && !$editing">
                                    <label for="estado" class="text-sm text-gray-700 dark:text-gray-300">Estado Activo</label>
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Detalle de Rubros --}}
                        <div class="border-l-4 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10 rounded-r-lg p-4 @if($contrato_tiene_primer_registro && !$editing) opacity-50 pointer-events-none @endif">
                            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-400 mb-3">Detalle de Rubros</h3>

                            {{-- Formulario para agregar rubro --}}
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-4">
                                <div class="relative" x-data="{ open: @entangle('showRubroDropdown') }">
                                    <x-label for="rubro_search" value="Rubro * (Buscar por nombre o código)" />
                                    <x-input id="rubro_search" type="text" wire:model.live="rubro_search" placeholder="Escriba para buscar..." class="w-full" autocomplete="off" />
                                    <input type="hidden" wire:model="new_rubro_id" />
                                    @if ($new_rubro_id)
                                        <button type="button" wire:click="$set('new_rubro_id', null); $set('rubro_search', '')" class="absolute right-2 top-8 text-gray-400 hover:text-gray-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                    @if ($showRubroDropdown && count($this->rubrosFiltrados) > 0)
                                        <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                            @foreach ($this->rubrosFiltrados as $r)
                                                <button type="button" wire:click="selectRubro({{ $r->id }})" class="w-full text-left px-3 py-2 hover:bg-violet-50 dark:hover:bg-gray-600 text-sm">
                                                    <span class="font-medium">{{ $r->codigo_rubro }}</span>
                                                    <span class="text-gray-500 dark:text-gray-400 ml-1">- {{ $r->nombre_rubro }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($showRubroDropdown && count($this->rubrosFiltrados) == 0 && strlen($rubro_search) >= 2)
                                        <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg p-3 text-sm text-gray-500">
                                            No se encontraron rubros.
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <x-label for="new_valor_rubro" value="Valor *" />
                                    <x-input id="new_valor_rubro" type="number" step="0.01" wire:model="new_valor_rubro" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="new_saldo_rubro" value="Saldo Actual" />
                                    <x-input id="new_saldo_rubro" type="number" step="0.01" wire:model="new_saldo_rubro" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="new_dependencia_afectacion" value="Dependencia Afectación" />
                                    <x-input id="new_dependencia_afectacion" type="text" wire:model="new_dependencia_afectacion" class="w-full" placeholder="Escriba la dependencia..." />
                                </div>
                            </div>
                            <div class="flex justify-end mb-4">
                                <x-button wire:click="addDetalle" class="bg-blue-600 hover:bg-blue-700">
                                    <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 16 16">
                                        <path d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                                    </svg>
                                    <span class="ml-2">Agregar Rubro</span>
                                </x-button>
                            </div>

                            {{-- Tabla de detalles --}}
                            @if (count($detalles) > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                <th class="px-4 py-2 text-left">#</th>
                                                <th class="px-4 py-2 text-left">Código</th>
                                                <th class="px-4 py-2 text-left">Nombre Rubro</th>
                                                <th class="px-4 py-2 text-right">Valor</th>
                                                <th class="px-4 py-2 text-right">Saldo</th>
                                                <th class="px-4 py-2 text-left">Dependencia</th>
                                                <th class="px-4 py-2 text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach ($detalles as $i => $detalle)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                    <td class="px-4 py-2">{{ $i + 1 }}</td>
                                                    <td class="px-4 py-2">{{ $detalle['codigo_rubro'] }}</td>
                                                    <td class="px-4 py-2 font-medium">{{ $detalle['nombre_rubro'] }}</td>
                                                    <td class="px-4 py-2 text-right">${{ number_format($detalle['valor_rubro'], 2, ',', '.') }}</td>
                                                    <td class="px-4 py-2 text-right">${{ number_format($detalle['saldo_rubro'], 2, ',', '.') }}</td>
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
                                                <td colspan="3" class="px-4 py-2 text-right">TOTALES:</td>
                                                <td class="px-4 py-2 text-right">${{ number_format($this->totalDetalles, 2, ',', '.') }}</td>
                                                <td class="px-4 py-2 text-right">${{ number_format($this->totalSaldo, 2, ',', '.') }}</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                                    No hay rubros agregados. Use el formulario superior para agregar detalles.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Botones de acción --}}
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <x-secondary-button wire:click="closeModal">
                            Cancelar
                        </x-secondary-button>
                        @if($contrato_tiene_primer_registro && !$editing)
                            <x-button disabled class="opacity-50 cursor-not-allowed">
                                Guardar
                            </x-button>
                        @else
                            <x-button wire:click="{{ $editing ? 'update' : 'store' }}">
                                {{ $editing ? 'Actualizar' : 'Guardar' }}
                            </x-button>
                        @endif
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
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">¿Está seguro que desea eliminar este registro? Esta acción no se puede deshacer.</p>
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
