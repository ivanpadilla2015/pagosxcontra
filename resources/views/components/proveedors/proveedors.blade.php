<?php

use App\Models\Proveedor;
use App\Models\Tipoper;
use App\Models\RegimenTributario;
use App\Models\Tipocuenta;
use App\Models\Retencion;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar el CRUD de "Proveedores" (proveedors).
 *
 * Basado en la lógica del componente de Regionales, pero para la entidad
 * Proveedor, que tiene tres relaciones (Tipo Persona, Régimen Tributario y
 * Tipo Cuenta) y varios campos propios (nit, email, etc.).
 * Archivo único que combina la lógica PHP y la vista Blade (forma de trabajar de Volt).
 */
new class extends Component
{
    // Habilita la paginación dentro del componente Livewire.
    use WithPagination;

    // ---------------------------------------------------------------------
    // PROPIEDADES (estado reactivo del componente)
    // ---------------------------------------------------------------------

    /**
     * Texto del buscador.
     * #[Url] refleja el valor en la URL (?search=...), permitiendo
     * compartir la búsqueda y conservarla al recargar la página.
     */
    #[Url]
    public string $search = '';

    /** Controla la visibilidad del modal de crear/editar (true = abierto). */
    public bool $modalOpen = false;

    /** Controla la visibilidad del modal de confirmación de eliminación. */
    public bool $deleteModalOpen = false;

    /**
     * ID del proveedor que se está editando.
     * null = modo "crear"; un entero = modo "editar".
     */
    public ?int $editingId = null;

    /** ID del proveedor seleccionado para eliminar (guardado al confirmar). */
    public ?int $proveedorToDeleteId = null;

    /** Nombre (campo 'nombre') del proveedor a eliminar, para mostrarlo en el modal. */
    public string $proveedorToDeleteName = '';

    // Campos del formulario
    public string $nombre = '';
    public string $nit = '';
    public ?string $digver = null;
    public string $email = '';
    public ?string $telefono = null;
    public ?string $direccion = null;
    public ?string $representante_legal = null;
    public ?int $tipoper_id = null;
    public ?int $regimen_tributario_id = null;
    public ?string $name_cuenta_bancaria = null;
    public ?string $numero_cuenta = null;
    public ?int $tipocuenta_id = null;

    /** Indica si el proveedor es declarante de retenciones. */
    public bool $es_declarante = true;

    /** Código de actividad económica (para retenciones parafiscales). */
    public ?string $codigo_actividad_economica = null;

    /** Descripción de la actividad económica. */
    public ?string $descripcion_actividad = null;

    /**
     * Indica si el proveedor usa retenciones personalizadas (excepción) en lugar
     * de las derivadas automáticamente de su régimen tributario.
     */
    public bool $tiene_excepcion_retenciones = false;

    /**
     * IDs de retenciones marcadas manualmente cuando hay excepción.
     *
     * @var array<int, int>
     */
    public array $retencionesSeleccionadas = [];

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    /**
     * Reglas de validación para los campos del formulario.
     * Livewire usa este método automáticamente al llamar a $this->validate().
     * 'nit' y 'email' son únicos; al editar se ignora el registro actual (ignore($id)).
     *
     * @return array Reglas aplicadas a todos los campos.
     */
    protected function rules(): array
    {
        $id = $this->editingId;

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:255', Rule::unique('proveedors')->ignore($id)],
            'digver' => ['nullable', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:255', Rule::unique('proveedors')->ignore($id)],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'tipoper_id' => ['required', 'exists:tipopers,id'],
            'regimen_tributario_id' => ['required', 'exists:regimen_tributarios,id'],
            'name_cuenta_bancaria' => ['nullable', 'string', 'max:255'],
            'numero_cuenta' => ['nullable', 'string', 'max:255'],
            'tipocuenta_id' => ['required', 'exists:tipocuentas,id'],
            'es_declarante' => ['boolean'],
            'codigo_actividad_economica' => ['nullable', 'string', 'max:20'],
            'descripcion_actividad' => ['nullable', 'string', 'max:500'],
            'tiene_excepcion_retenciones' => ['boolean'],
            'retencionesSeleccionadas' => ['array'],
            'retencionesSeleccionadas.*' => ['exists:retenciones,id'],
        ];
    }

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (datos derivados / consultas)
    // ---------------------------------------------------------------------

    /**
     * Devuelve la lista paginada de proveedores.
     * #[Computed] cachea el resultado durante el mismo render.
     *
     * - Carga eager loading de las tres relaciones para evitar N+1.
     * - Filtra por $search en nombre/nit/email o en el nombre de las relaciones.
     * - Ordena por los más recientes (latest).
     * - Pagina de 10 en 10.
     */
    #[Computed]
    public function proveedors()
    {
        return Proveedor::query()
            ->with(['tipoper', 'regimenTributario', 'tipocuenta'])
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('nit', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('tipoper', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    })
                    ->orWhereHas('regimenTributario', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    })
                    ->orWhereHas('tipocuenta', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    });
            })
            ->latest()
            ->paginate(10);
    }

    /** Lista de tipos de persona (para el select del formulario). */
    #[Computed]
    public function tipopers()
    {
        return Tipoper::orderBy('name')->get();
    }

    /** Lista de regímenes tributarios (para el select del formulario). */
    #[Computed]
    public function regimenTributarios()
    {
        return RegimenTributario::orderBy('name')->get();
    }

    /** Lista de tipos de cuenta (para el select del formulario). */
    #[Computed]
    public function tipocuentas()
    {
        return Tipocuenta::orderBy('name')->get();
    }

    /** Catálogo completo de retenciones (para los checkboxes de excepción). */
    #[Computed]
    public function retenciones()
    {
        return Retencion::orderBy('name')->get();
    }

    /**
     * Retenciones derivadas del régimen seleccionado actualmente en el formulario.
     * Se recalcula en cada render, por lo que refleja el select en tiempo real.
     */
    #[Computed]
    public function retencionesDelRegimen()
    {
        if (! $this->regimen_tributario_id) {
            return collect();
        }

        return RegimenTributario::find($this->regimen_tributario_id)
            ?->retenciones()->orderBy('name')->get() ?? collect();
    }

    // ---------------------------------------------------------------------
    // CICLO DE VIDA / REACCIONES
    // ---------------------------------------------------------------------

    /**
     * Se ejecuta automáticamente cuando cambia $search. Reinicia la paginación.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL CREAR / EDITAR
    // ---------------------------------------------------------------------

    /**
     * Abre el modal de crear o editar.
     *
     * @param int|null $id ID del proveedor a editar. Si es null, es modo crear.
     */
    public function openModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset([
            'nombre', 'nit', 'digver', 'email', 'telefono', 'direccion',
            'representante_legal', 'tipoper_id', 'regimen_tributario_id',
            'name_cuenta_bancaria', 'numero_cuenta', 'tipocuenta_id',
            'es_declarante', 'codigo_actividad_economica', 'descripcion_actividad',
            'tiene_excepcion_retenciones', 'retencionesSeleccionadas', 'editingId',
        ]);
        $this->editingId = $id;

        if ($id) {
            $proveedor = Proveedor::findOrFail($id);
            $this->nombre = $proveedor->nombre;
            $this->nit = $proveedor->nit;
            $this->digver = $proveedor->digver;
            $this->email = $proveedor->email;
            $this->telefono = $proveedor->telefono;
            $this->direccion = $proveedor->direccion;
            $this->representante_legal = $proveedor->representante_legal;
            $this->tipoper_id = $proveedor->tipoper_id;
            $this->regimen_tributario_id = $proveedor->regimen_tributario_id;
            $this->name_cuenta_bancaria = $proveedor->name_cuenta_bancaria;
            $this->numero_cuenta = $proveedor->numero_cuenta;
            $this->tipocuenta_id = $proveedor->tipocuenta_id;
            $this->es_declarante = $proveedor->es_declarante;
            $this->codigo_actividad_economica = $proveedor->codigo_actividad_economica;
            $this->descripcion_actividad = $proveedor->descripcion_actividad;
            $this->tiene_excepcion_retenciones = $proveedor->tiene_excepcion_retenciones;
            $this->retencionesSeleccionadas = $proveedor->retencionesExcepcion()->pluck('retenciones.id')->toArray();
        }

        $this->modalOpen = true;
    }

    /** Cierra el modal de crear/editar y limpia el formulario y errores. */
    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset([
            'nombre', 'nit', 'digver', 'email', 'telefono', 'direccion',
            'representante_legal', 'tipoper_id', 'regimen_tributario_id',
            'name_cuenta_bancaria', 'numero_cuenta', 'tipocuenta_id',
            'es_declarante', 'codigo_actividad_economica', 'descripcion_actividad',
            'tiene_excepcion_retenciones', 'retencionesSeleccionadas', 'editingId',
        ]);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    /**
     * Prepara la eliminación mostrando el modal de confirmación.
     * Livewire resuelve el modelo Proveedor automáticamente desde el ID.
     *
     * @param Proveedor $proveedor Modelo inyectado por Livewire.
     */
    public function confirmDelete(Proveedor $proveedor): void
    {
        $this->proveedorToDeleteId = $proveedor->id;
        $this->proveedorToDeleteName = $proveedor->nombre;
        $this->deleteModalOpen = true;
    }

    /** Cierra el modal de eliminación y limpia las variables temporales. */
    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->proveedorToDeleteId = null;
        $this->proveedorToDeleteName = '';
    }

    /**
     * Elimina definitivamente el proveedor seleccionado y muestra un mensaje.
     * Usa findOrFail para lanzar 404 si el ID ya no existe.
     */
    public function delete(): void
    {
        Proveedor::findOrFail($this->proveedorToDeleteId)->delete();
        session()->flash('message', 'Proveedor eliminado correctamente.');
        $this->closeDeleteModal();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    /**
     * Valida el formulario y crea o actualiza el proveedor según corresponda.
     * Después cierra el modal y muestra un mensaje de éxito.
     */
    public function save(): void
    {
        $data = $this->validate();

        // retencionesSeleccionadas no es columna de proveedors: se sincroniza aparte.
        unset($data['retencionesSeleccionadas']);

        if ($this->editingId) {
            $proveedor = Proveedor::findOrFail($this->editingId);
            $proveedor->update($data);
            session()->flash('message', 'Proveedor actualizado correctamente.');
        } else {
            $proveedor = Proveedor::create($data);
            session()->flash('message', 'Proveedor creado correctamente.');
        }

        // Excepciones (Derivación A): si el proveedor usa retenciones personalizadas
        // se guardan en el pivote; si no, se limpian para mantener la derivación del régimen.
        if ($this->tiene_excepcion_retenciones) {
            $proveedor->retencionesExcepcion()->sync($this->retencionesSeleccionadas);
        } else {
            $proveedor->retencionesExcepcion()->sync([]);
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
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Proveedores</h1>
        <button type="button" wire:click="openModal()" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Nuevo Proveedor</button>
    </div>

    {{-- Mensaje de éxito flash --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Buscador --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Buscar nombre, NIT, email, tipo..." class="form-input w-full max-w-xs" />
    </div>

    {{-- Tabla de proveedores --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">NIT</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Tipo Persona</th>
                    <th class="px-4 py-3 text-left">Régimen</th>
                    <th class="px-4 py-3 text-left">Tipo Cuenta</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->proveedors as $proveedor)
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->nombre }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->nit }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->email }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->tipoper->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->regimenTributario->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $proveedor->tipocuenta->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="openModal({{ $proveedor->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $proveedor->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay proveedores registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->proveedors->links() }}
    </div>

    {{-- Modal de crear/editar --}}
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/60 overflow-y-auto py-8" wire:click="closeModal" wire:key="proveedor-modal">
            <div class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 my-auto max-h-[90vh] overflow-y-auto" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar Proveedor' : 'Nuevo Proveedor' }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="nombre" class="form-input w-full @error('nombre') border-rose-500 @enderror" />
                        @error('nombre') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIT <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="nit" class="form-input w-full @error('nit') border-rose-500 @enderror" />
                        @error('nit') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dígito de verificación</label>
                        <input type="text" wire:model="digver" class="form-input w-full @error('digver') border-rose-500 @enderror" />
                        @error('digver') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="email" class="form-input w-full @error('email') border-rose-500 @enderror" />
                        @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                        <input type="text" wire:model="telefono" class="form-input w-full @error('telefono') border-rose-500 @enderror" />
                        @error('telefono') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                        <input type="text" wire:model="direccion" class="form-input w-full @error('direccion') border-rose-500 @enderror" />
                        @error('direccion') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Representante Legal</label>
                        <input type="text" wire:model="representante_legal" class="form-input w-full @error('representante_legal') border-rose-500 @enderror" />
                        @error('representante_legal') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo Persona <span class="text-rose-500">*</span></label>
                        <select wire:model="tipoper_id" class="form-input w-full @error('tipoper_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->tipopers as $tipoper)
                                <option value="{{ $tipoper->id }}">{{ $tipoper->name }}</option>
                            @endforeach
                        </select>
                        @error('tipoper_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Régimen Tributario <span class="text-rose-500">*</span></label>
                        <select wire:model.live="regimen_tributario_id" class="form-input w-full @error('regimen_tributario_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->regimenTributarios as $regimen)
                                <option value="{{ $regimen->id }}">{{ $regimen->name }}</option>
                            @endforeach
                        </select>
                        @error('regimen_tributario_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre Cuenta Bancaria</label>
                        <input type="text" wire:model="name_cuenta_bancaria" class="form-input w-full @error('name_cuenta_bancaria') border-rose-500 @enderror" />
                        @error('name_cuenta_bancaria') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Cuenta</label>
                        <input type="text" wire:model="numero_cuenta" class="form-input w-full @error('numero_cuenta') border-rose-500 @enderror" />
                        @error('numero_cuenta') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo Cuenta <span class="text-rose-500">*</span></label>
                        <select wire:model="tipocuenta_id" class="form-input w-full @error('tipocuenta_id') border-rose-500 @enderror">
                            <option value="">Seleccionar...</option>
                            @foreach ($this->tipocuentas as $tipocuenta)
                                <option value="{{ $tipocuenta->id }}">{{ $tipocuenta->name }}</option>
                            @endforeach
                        </select>
                        @error('tipocuenta_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="es_declarante_check" class="flex items-center gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors {{ $es_declarante ? 'border-violet-400 bg-violet-50 dark:border-violet-500/60 dark:bg-violet-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700/60 dark:bg-gray-900/30 hover:border-gray-300 dark:hover:border-gray-600' }}">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ $es_declarante ? 'bg-violet-100 dark:bg-violet-900/40' : 'bg-gray-200 dark:bg-gray-700/60' }}">
                                <svg class="w-5 h-5 {{ $es_declarante ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="es_declarante_check" wire:model="es_declarante" class="form-checkbox w-5 h-5 text-violet-500 border-violet-400 focus:ring-violet-400" />
                                    <span class="font-semibold text-sm {{ $es_declarante ? 'text-violet-800 dark:text-violet-200' : 'text-gray-700 dark:text-gray-300' }}">Es declarante de retenciones</span>
                                </div>
                                <p class="text-xs mt-0.5 {{ $es_declarante ? 'text-violet-600 dark:text-violet-400' : 'text-gray-400 dark:text-gray-500' }}">Afecta los porcentajes de Retefuente y otras retenciones según régimen</p>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código Actividad Económica</label>
                        <input type="text" wire:model="codigo_actividad_economica" class="form-input w-full @error('codigo_actividad_economica') border-rose-500 @enderror" placeholder="Ej: 0111" />
                        @error('codigo_actividad_economica') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción Actividad</label>
                        <input type="text" wire:model="descripcion_actividad" class="form-input w-full @error('descripcion_actividad') border-rose-500 @enderror" placeholder="Ej: Cultivo de arroz" />
                        @error('descripcion_actividad') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- ============================================================= --}}
                {{-- RETENCIONES (Derivación A: por régimen o excepción manual)    --}}
                {{-- ============================================================= --}}
                <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700/60 p-4">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Retenciones aplicables</h3>

                    {{-- Retenciones derivadas automáticamente del régimen seleccionado --}}
                    <div class="mb-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Según el régimen tributario:</p>
                        @forelse ($this->retencionesDelRegimen as $retencion)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5 mr-1 mb-1">{{ $retencion->name }}</span>
                        @empty
                            <span class="text-xs text-gray-400 dark:text-gray-500">Ninguna (selecciona un régimen).</span>
                        @endforelse
                    </div>

                    {{-- Interruptor de excepción --}}
                    <label class="flex items-center mb-2">
                        <input type="checkbox" wire:model.live="tiene_excepcion_retenciones" class="form-checkbox" />
                        <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">Usar retenciones personalizadas (excepción)</span>
                    </label>

                    {{-- Checkboxes manuales, solo visibles con excepción activa --}}
                    @if ($tiene_excepcion_retenciones)
                        <div class="space-y-2 rounded-lg border border-gray-200 dark:border-gray-700/60 p-3 mt-2">
                            @foreach ($this->retenciones as $retencion)
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="retencionesSeleccionadas" value="{{ $retencion->id }}" class="form-checkbox" />
                                    <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">{{ $retencion->name }}</span>
                                </label>
                            @endforeach
                            <p class="text-xs text-gray-500 dark:text-gray-400">Estas reemplazan a las derivadas del régimen para este proveedor.</p>
                        </div>
                    @endif
                </div>

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
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Proveedor</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar a <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $proveedorToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
