<?php

use App\Models\Contrato;
use App\Models\Proveedor;
use App\Models\Tipocontrato;
use App\Models\Contrainter;
use App\Models\User;
use App\Traits\FiltrablePorRegional;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar el CRUD de "Contratos".
 *
 * Entidad con cuatro relaciones: Proveedor, Tipo de Contrato, Contrato Inter
 * y Usuario. Archivo único que combina la lógica PHP y la vista Blade.
 */
new class extends Component
{
    // Habilita la paginación dentro del componente Livewire.
    use WithPagination;
    use FiltrablePorRegional;

    // ---------------------------------------------------------------------
    // PROPIEDADES (estado reactivo del componente)
    // ---------------------------------------------------------------------

    /**
     * Texto del buscador. #[Url] refleja el valor en la URL (?search=...).
     */
    #[Url]
    public string $search = '';

    /** Controla la visibilidad del modal de crear/editar. */
    public bool $modalOpen = false;

    /** Controla la visibilidad del modal de confirmación de eliminación. */
    public bool $deleteModalOpen = false;

    /** ID del contrato en edición. null = modo crear. */
    public ?int $editingId = null;

    /** ID del contrato seleccionado para eliminar. */
    public ?int $contratoToDeleteId = null;

    /** Número del contrato a eliminar, para mostrarlo en el modal. */
    public string $contratoToDeleteName = '';

    // Campos del formulario
    public string $numcontrato = '';
    public ?string $fechacontrato = null;
    public ?string $fecha_inicio_contrato = null;
    public ?string $fecha_fin_contrato = null;
    public string $objetocontrato = '';
    public int $num_mes = 0;
    public int $cansecu_pagos = 0;
    public int $cansecu_infor = 0;
    public int $cansecu_tramite = 0;
    public ?string $numero_poliza = null;
    public ?string $valor_poliza_asegurado = null;
    public ?string $fecha_poliza_inicio = null;
    public ?string $fecha_poliza_fin = null;
    public ?string $sape_acreedor = null;
    public ?string $orden_erp_sap = null;
    public ?string $expediente_orfeo = null;
    public ?int $proveedor_id = null;
    public ?int $tipocontrato_id = null;
    public ?int $contrainter_id = null;
    public ?int $user_id = null;

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    /**
     * Reglas de validación. 'numcontrato' es único; al editar se ignora
     * el registro actual (ignore($id)).
     *
     * @return array
     */
    protected function rules(): array
    {
        $id = $this->editingId;

        return [
            'numcontrato' => ['required', 'string', 'max:50', Rule::unique('contratos')->ignore($id)],
            'fechacontrato' => ['required', 'date'],
            'fecha_inicio_contrato' => ['required', 'date'],
            'fecha_fin_contrato' => ['required', 'date', 'after_or_equal:fecha_inicio_contrato'],
            'objetocontrato' => ['required', 'string'],
            'num_mes' => ['required', 'integer', 'min:0'],
            'cansecu_pagos' => ['required', 'integer', 'min:0'],
            'cansecu_infor' => ['required', 'integer', 'min:0'],
            'cansecu_tramite' => ['required', 'integer', 'min:0'],
            'numero_poliza' => ['nullable', 'string', 'max:50'],
            'valor_poliza_asegurado' => ['nullable', 'numeric', 'min:0'],
            'fecha_poliza_inicio' => ['nullable', 'date'],
            'fecha_poliza_fin' => ['nullable', 'date', 'after_or_equal:fecha_poliza_inicio'],
            'sape_acreedor' => ['nullable', 'string', 'max:20'],
            'orden_erp_sap' => ['nullable', 'string', 'max:255'],
            'expediente_orfeo' => ['nullable', 'string', 'max:255'],
            'proveedor_id' => ['required', 'exists:proveedors,id'],
            'tipocontrato_id' => ['required', 'exists:tipocontratos,id'],
            'contrainter_id' => ['required', 'exists:contrainters,id'],
            'user_id' => ['required', 'exists:users,id'],
        ];
    }

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (datos derivados / consultas)
    // ---------------------------------------------------------------------

    /**
     * Lista paginada de contratos con eager loading de las 4 relaciones.
     */
    #[Computed]
    public function contratos()
    {
        return Contrato::query()
            ->with(['proveedor', 'tipocontrato', 'contrainter', 'user'])
            ->when($this->search, function ($query) {
                $query->where('numcontrato', 'like', '%'.$this->search.'%')
                    ->orWhere('objetocontrato', 'like', '%'.$this->search.'%')
                    ->orWhereHas('proveedor', function ($q) {
                        $q->where('nombre', 'like', '%'.$this->search.'%');
                    })
                    ->orWhereHas('tipocontrato', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    });
            })
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->latest()
            ->paginate(10);
    }

    /** Lista de proveedores (para el select del formulario). */
    #[Computed]
    public function proveedors()
    {
        return Proveedor::orderBy('nombre')->get();
    }

    /** Lista de tipos de contrato (para el select del formulario). */
    #[Computed]
    public function tipocontratos()
    {
        return Tipocontrato::orderBy('name')->get();
    }

    /** Lista de contratos inter (para el select del formulario). */
    #[Computed]
    public function contrainters()
    {
        return Contrainter::orderBy('detalle')->get();
    }

    /** Lista de usuarios (para el select del formulario). */
    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    // ---------------------------------------------------------------------
    // CICLO DE VIDA / REACCIONES
    // ---------------------------------------------------------------------

    /** Se ejecuta al cambiar $search. Reinicia la paginación. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL CREAR / EDITAR
    // ---------------------------------------------------------------------

    /** Campos del formulario que se limpian al abrir/cerrar el modal. */
    protected array $formFields = [
        'numcontrato', 'fechacontrato', 'fecha_inicio_contrato', 'fecha_fin_contrato',
        'objetocontrato', 'num_mes', 'cansecu_pagos', 'cansecu_infor', 'cansecu_tramite',
        'numero_poliza', 'valor_poliza_asegurado', 'fecha_poliza_inicio', 'fecha_poliza_fin',
        'sape_acreedor', 'orden_erp_sap', 'expediente_orfeo', 'proveedor_id',
        'tipocontrato_id', 'contrainter_id', 'user_id', 'editingId',
    ];

    /**
     * Abre el modal de crear o editar.
     *
     * @param int|null $id ID del contrato a editar. Si es null, es modo crear.
     */
    public function openModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset($this->formFields);
        $this->editingId = $id;

        if ($id) {
            $contrato = Contrato::findOrFail($id);
            $this->numcontrato = $contrato->numcontrato;
            $this->fechacontrato = $contrato->fechacontrato?->format('Y-m-d');
            $this->fecha_inicio_contrato = $contrato->fecha_inicio_contrato?->format('Y-m-d');
            $this->fecha_fin_contrato = $contrato->fecha_fin_contrato?->format('Y-m-d');
            $this->objetocontrato = $contrato->objetocontrato;
            $this->num_mes = $contrato->num_mes;
            $this->cansecu_pagos = $contrato->cansecu_pagos;
            $this->cansecu_infor = $contrato->cansecu_infor;
            $this->cansecu_tramite = $contrato->cansecu_tramite;
            $this->numero_poliza = $contrato->numero_poliza;
            $this->valor_poliza_asegurado = $contrato->valor_poliza_asegurado;
            $this->fecha_poliza_inicio = $contrato->fecha_poliza_inicio?->format('Y-m-d');
            $this->fecha_poliza_fin = $contrato->fecha_poliza_fin?->format('Y-m-d');
            $this->sape_acreedor = $contrato->sape_acreedor;
            $this->orden_erp_sap = $contrato->orden_erp_sap;
            $this->expediente_orfeo = $contrato->expediente_orfeo;
            $this->proveedor_id = $contrato->proveedor_id;
            $this->tipocontrato_id = $contrato->tipocontrato_id;
            $this->contrainter_id = $contrato->contrainter_id;
            $this->user_id = $contrato->user_id;
        }

        $this->modalOpen = true;
    }

    /** Cierra el modal de crear/editar y limpia el formulario y errores. */
    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset($this->formFields);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    /**
     * Prepara la eliminación mostrando el modal de confirmación.
     *
     * @param Contrato $contrato Modelo inyectado por Livewire.
     */
    public function confirmDelete(Contrato $contrato): void
    {
        $this->contratoToDeleteId = $contrato->id;
        $this->contratoToDeleteName = $contrato->numcontrato;
        $this->deleteModalOpen = true;
    }

    /** Cierra el modal de eliminación y limpia las variables temporales. */
    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->contratoToDeleteId = null;
        $this->contratoToDeleteName = '';
    }

    /** Elimina definitivamente el contrato seleccionado y muestra un mensaje. */
    public function delete(): void
    {
        Contrato::findOrFail($this->contratoToDeleteId)->delete();
        session()->flash('message', 'Contrato eliminado correctamente.');
        $this->closeDeleteModal();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    /**
     * Valida el formulario y crea o actualiza el contrato según corresponda.
     */
    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Contrato::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Contrato actualizado correctamente.');
        } else {
            Contrato::create($data);
            session()->flash('message', 'Contrato creado correctamente.');
        }

        $this->closeModal();
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Contratos</h1>
        <button type="button" wire:click="openModal()" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Nuevo Contrato</button>
    </div>

    {{-- Mensaje de éxito flash --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Buscador --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Buscar número, objeto, proveedor, tipo..." class="form-input w-full max-w-xs" />
    </div>

    {{-- Tabla de contratos --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-x-auto">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">N° Contrato</th>
                    <th class="px-4 py-3 text-left">Proveedor</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Inicio</th>
                    <th class="px-4 py-3 text-left">Fin</th>
                    <th class="px-4 py-3 text-left">Responsable</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->contratos as $contrato)
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->tipocontrato->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->fecha_inicio_contrato?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->fecha_fin_contrato?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $contrato->user->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="openModal({{ $contrato->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $contrato->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay contratos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->contratos->links() }}
    </div>

    {{-- Modal de crear/editar --}}
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/60 overflow-y-auto py-8" wire:click="closeModal" wire:key="contrato-modal">
            <div class="w-full max-w-3xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">{{ $editingId ? 'Editar Contrato' : 'Nuevo Contrato' }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Los campos marcados con <span class="text-rose-500">*</span> son obligatorios.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° Contrato <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="numcontrato" class="form-input w-full @error('numcontrato') border-rose-500 @enderror" />
                        @error('numcontrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Contrato <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model="fechacontrato" class="form-input w-full @error('fechacontrato') border-rose-500 @enderror" />
                        @error('fechacontrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Inicio <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model="fecha_inicio_contrato" class="form-input w-full @error('fecha_inicio_contrato') border-rose-500 @enderror" />
                        @error('fecha_inicio_contrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Fin <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model="fecha_fin_contrato" class="form-input w-full @error('fecha_fin_contrato') border-rose-500 @enderror" />
                        @error('fecha_fin_contrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Objeto del Contrato <span class="text-rose-500">*</span></label>
                        <textarea wire:model="objetocontrato" rows="2" class="form-textarea w-full @error('objetocontrato') border-rose-500 @enderror"></textarea>
                        @error('objetocontrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor <span class="text-rose-500">*</span></label>
                        <select wire:model="proveedor_id" class="form-select w-full @error('proveedor_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->proveedors as $proveedor)
                                <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                            @endforeach
                        </select>
                        @error('proveedor_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de Contrato <span class="text-rose-500">*</span></label>
                        <select wire:model="tipocontrato_id" class="form-select w-full @error('tipocontrato_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->tipocontratos as $tipocontrato)
                                <option value="{{ $tipocontrato->id }}">{{ $tipocontrato->name }}</option>
                            @endforeach
                        </select>
                        @error('tipocontrato_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contrato Inter <span class="text-rose-500">*</span></label>
                        <select wire:model="contrainter_id" class="form-select w-full @error('contrainter_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->contrainters as $contrainter)
                                <option value="{{ $contrainter->id }}">{{ $contrainter->detalle }}</option>
                            @endforeach
                        </select>
                        @error('contrainter_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Responsable <span class="text-rose-500">*</span></label>
                        <select wire:model="user_id" class="form-select w-full @error('user_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mes Inicio Pagos <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="num_mes" class="form-input w-full @error('num_mes') border-rose-500 @enderror" />
                        @error('num_mes') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Consecutivo Pagos <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="cansecu_pagos" class="form-input w-full @error('cansecu_pagos') border-rose-500 @enderror" />
                        @error('cansecu_pagos') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Consecutivo Informes <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="cansecu_infor" class="form-input w-full @error('cansecu_infor') border-rose-500 @enderror" />
                        @error('cansecu_infor') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Consecutivo Trámite <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="cansecu_tramite" class="form-input w-full @error('cansecu_tramite') border-rose-500 @enderror" />
                        @error('cansecu_tramite') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Sección destacada: Póliza e información administrativa --}}
                <fieldset class="mt-6 rounded-xl border-2 border-violet-300 dark:border-violet-500/60 bg-violet-50/60 dark:bg-violet-900/10 p-4">
                    <legend class="px-2 text-sm font-semibold text-violet-700 dark:text-violet-300">Póliza e Información Administrativa</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número Póliza</label>
                            <input type="text" wire:model="numero_poliza" class="form-input w-full @error('numero_poliza') border-rose-500 @enderror" />
                            @error('numero_poliza') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Asegurado</label>
                            <input type="number" step="0.01" wire:model="valor_poliza_asegurado" class="form-input w-full @error('valor_poliza_asegurado') border-rose-500 @enderror" />
                            @error('valor_poliza_asegurado') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Póliza Inicio</label>
                            <input type="date" wire:model="fecha_poliza_inicio" class="form-input w-full @error('fecha_poliza_inicio') border-rose-500 @enderror" />
                            @error('fecha_poliza_inicio') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Póliza Fin</label>
                            <input type="date" wire:model="fecha_poliza_fin" class="form-input w-full @error('fecha_poliza_fin') border-rose-500 @enderror" />
                            @error('fecha_poliza_fin') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SAP Acreedor</label>
                            <input type="text" wire:model="sape_acreedor" class="form-input w-full @error('sape_acreedor') border-rose-500 @enderror" />
                            @error('sape_acreedor') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Orden ERP/SAP</label>
                            <input type="text" wire:model="orden_erp_sap" class="form-input w-full @error('orden_erp_sap') border-rose-500 @enderror" />
                            @error('orden_erp_sap') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expediente Orfeo</label>
                            <input type="text" wire:model="expediente_orfeo" class="form-input w-full @error('expediente_orfeo') border-rose-500 @enderror" />
                            @error('expediente_orfeo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="save" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de confirmación de eliminación --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal" wire:key="delete-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Contrato</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar el contrato <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contratoToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
