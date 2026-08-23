<?php

use App\Models\Regional;
use App\Models\Director;
use App\Models\Presupuesto;
use App\Models\Municipio;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar el CRUD de "Regionales".
 *
 * Una Regional tiene dos relaciones: un Director y un Presupuesto, por eso
 * este componente carga listas de ambos para llenar los selects del formulario.
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
     * ID de la regional que se está editando.
     * null = modo "crear"; un entero = modo "editar".
     */
    public ?int $editingId = null;

    /** ID de la regional seleccionada para eliminar (guardado al confirmar). */
    public ?int $regionalToDeleteId = null;

    /** Nombre de la regional a eliminar, usado solo para mostrarlo en el modal. */
    public string $regionalToDeleteName = '';

    /** Campo del formulario: nombre de la regional. */
    public string $name = '';

    /** Campo del formulario: ID del director seleccionado (clave foránea). */
    public ?int $director_id = null;

    /** Campo del formulario: ID del presupuesto seleccionado (clave foránea). */
    public ?int $presupuesto_id = null;

    /** Campo del formulario: ID del municipio sede de la regional. */
    public ?int $municipio_id = null;

    /** Campo del formulario: nombre del coordinador administrativo. */
    public ?string $firma_nombre_coord_admin = null;

    /** Campo del formulario: cargo del coordinador administrativo. */
    public ?string $firma_cargo_admin = null;

    /** Campo del formulario: nombre del coordinador de contrato. */
    public ?string $firma_nombre_coord_contrato = null;

    /** Campo del formulario: cargo del coordinador de contrato. */
    public ?string $firma_cargo_contrato = null;

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    /**
     * Reglas de validación para los campos del formulario.
     * Livewire usa este método automáticamente al llamar a $this->validate().
     * 'director_id' y 'presupuesto_id' deben existir en sus tablas (exists).
     *
     * @return array Reglas aplicadas a 'name', 'director_id' y 'presupuesto_id'.
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'director_id' => ['required', 'exists:directors,id'],
            'presupuesto_id' => ['required', 'exists:presupuestos,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'firma_nombre_coord_admin' => ['nullable', 'string', 'max:255'],
            'firma_cargo_admin' => ['nullable', 'string', 'max:255'],
            'firma_nombre_coord_contrato' => ['nullable', 'string', 'max:255'],
            'firma_cargo_contrato' => ['nullable', 'string', 'max:255'],
        ];
    }

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (datos derivados / consultas)
    // ---------------------------------------------------------------------

    /**
     * Devuelve la lista paginada de regionales.
     * #[Computed] cachea el resultado durante el mismo render para no
     * ejecutar la consulta varias veces (se usa en la tabla y en los links).
     *
     * - Carga eager loading de 'director' y 'presupuesto' para evitar N+1.
     * - Filtra por $search en 'name' o en el nombre del director/presupuesto
     *   relacionado (orWhereHas).
     * - Ordena por los más recientes (latest = created_at desc).
     * - Pagina de 10 en 10.
     */
    #[Computed]
    public function regionals()
    {
        return Regional::query()
            ->with(['director', 'presupuesto', 'municipio'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('director', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    })
                    ->orWhereHas('presupuesto', function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    });
            })
            ->latest()
            ->paginate(10);
    }

    /**
     * Lista de directores ordenados por nombre.
     * Se usa para llenar el <select> del formulario de crear/editar.
     */
    #[Computed]
    public function directors()
    {
        return Director::orderBy('name')->get();
    }

    /**
     * Lista de presupuestos ordenados por nombre.
     * Se usa para llenar el <select> del formulario de crear/editar.
     */
    #[Computed]
    public function presupuestos()
    {
        return Presupuesto::orderBy('name')->get();
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('nombre')->get();
    }

    // ---------------------------------------------------------------------
    // CICLO DE VIDA / REACCIONES
    // ---------------------------------------------------------------------

    /**
     * Se ejecuta automáticamente cuando cambia $search (gracias al prefijo
     * "updated"). Reinicia la paginación para que la búsqueda empiece
     * desde la página 1.
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
     * @param int|null $id ID de la regional a editar. Si es null, es modo crear.
     */
    public function openModal(?int $id = null): void
    {
        // Limpia errores de validación previos y los campos del formulario.
        $this->resetValidation();
        $this->reset(['name', 'director_id', 'presupuesto_id', 'municipio_id', 'firma_nombre_coord_admin', 'firma_cargo_admin', 'firma_nombre_coord_contrato', 'firma_cargo_contrato', 'editingId']);
        $this->editingId = $id;

        // Si recibimos un ID, cargamos los datos de la regional a editar.
        if ($id) {
            $regional = Regional::findOrFail($id);
            $this->name = $regional->name;
            $this->director_id = $regional->director_id;
            $this->presupuesto_id = $regional->presupuesto_id;
            $this->municipio_id = $regional->municipio_id;
            $this->firma_nombre_coord_admin = $regional->firma_nombre_coord_admin;
            $this->firma_cargo_admin = $regional->firma_cargo_admin;
            $this->firma_nombre_coord_contrato = $regional->firma_nombre_coord_contrato;
            $this->firma_cargo_contrato = $regional->firma_cargo_contrato;
        }

        $this->modalOpen = true;
    }

    /** Cierra el modal de crear/editar y limpia el formulario y errores. */
    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset(['name', 'director_id', 'presupuesto_id', 'municipio_id', 'firma_nombre_coord_admin', 'firma_cargo_admin', 'firma_nombre_coord_contrato', 'firma_cargo_contrato', 'editingId']);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    /**
     * Prepara la eliminación mostrando el modal de confirmación.
     * Livewire resuelve el modelo Regional automáticamente desde el ID
     * que se le pasa en el botón (wire:click="confirmDelete({{ $regional->id }})").
     *
     * @param Regional $regional Modelo inyectado por Livewire.
     */
    public function confirmDelete(Regional $regional): void
    {
        $this->regionalToDeleteId = $regional->id;
        $this->regionalToDeleteName = $regional->name;
        $this->deleteModalOpen = true;
    }

    /** Cierra el modal de eliminación y limpia las variables temporales. */
    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->regionalToDeleteId = null;
        $this->regionalToDeleteName = '';
    }

    /**
     * Elimina definitivamente la regional seleccionada y muestra un mensaje.
     * Usa findOrFail para lanzar 404 si el ID ya no existe.
     * (Las regionales asociadas al director/presupuesto se borran en cascada
     * según la migración, pero aquí solo se elimina la regional en sí.)
     */
    public function delete(): void
    {
        Regional::findOrFail($this->regionalToDeleteId)->delete();
        session()->flash('message', 'Regional eliminada correctamente.');
        $this->closeDeleteModal();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    /**
     * Valida el formulario y crea o actualiza la regional según corresponda.
     * Después cierra el modal y muestra un mensaje de éxito.
     */
    public function save(): void
    {
        // Valida usando las reglas de rules(); si falla, Livewire detiene
        // la ejecución y muestra los errores en la vista.
        $data = $this->validate();

        if ($this->editingId) {
            // Modo edición: actualiza el registro existente.
            Regional::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Regional actualizada correctamente.');
        } else {
            // Modo creación: inserta un nuevo registro.
            Regional::create($data);
            session()->flash('message', 'Regional creada correctamente.');
        }

        $this->closeModal();
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado: título de la página y botón "Nueva Regional" --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Regionales</h1>
        {{-- Abre el modal en modo crear (sin ID) --}}
        <button type="button" wire:click="openModal()" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Nueva Regional</button>
    </div>

    {{-- Mensaje de éxito temporal (flash) mostrado tras crear/editar/eliminar --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Campo de búsqueda: wire:model.live actualiza $search en tiempo real.
         Busca por nombre de regional, director o presupuesto. --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Buscar regional, director o presupuesto..." class="form-input w-full max-w-xs" />
    </div>

    {{-- Tabla con la lista paginada de regionales (usa la propiedad computada $this->regionals).
         Muestra el nombre y, vía relaciones, el nombre del director y del presupuesto. --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Director</th>
                    <th class="px-4 py-3 text-left">Presupuesto</th>
                    <th class="px-4 py-3 text-left">Municipio Sede</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                {{-- Recorre las regionales; @empty muestra mensaje si no hay resultados --}}
                @forelse ($this->regionals as $regional)
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $regional->name }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $regional->director->name }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $regional->presupuesto->name }}</td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $regional->municipio->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            {{-- Botón editar: abre el modal pasando el ID --}}
                            <button type="button" wire:click="openModal({{ $regional->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            {{-- Botón eliminar: dispara el modal de confirmación --}}
                            <button type="button" wire:click="confirmDelete({{ $regional->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay regionales registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Controles de paginación --}}
    <div class="mt-4">
        {{ $this->regionals->links() }}
    </div>

    {{-- Modal de crear/editar (se muestra solo si $modalOpen es true) --}}
    @if ($modalOpen)
        {{-- Clic fuera del modal lo cierra; wire:click.stop evita cerrar al pulsar dentro --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="regional-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg flex flex-col max-h-[90vh]" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 px-6 pt-6 pb-4 shrink-0">{{ $editingId ? 'Editar Regional' : 'Nueva Regional' }}</h2>

                <div class="space-y-4 px-6 overflow-y-auto flex-1 min-h-0">
                    {{-- Campo Nombre --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
                        <input type="text" wire:model="name" class="form-input w-full @error('name') border-rose-500 @enderror" />
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    {{-- Campo Director: select llenado con la propiedad computada $this->directors --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Director</label>
                        <select wire:model="director_id" class="form-input w-full @error('director_id') border-rose-500 @enderror">
                            <option value="">Seleccionar director...</option>
                            @foreach ($this->directors as $director)
                                <option value="{{ $director->id }}">{{ $director->name }}</option>
                            @endforeach
                        </select>
                        @error('director_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    {{-- Campo Presupuesto: select llenado con la propiedad computada $this->presupuestos --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Presupuesto</label>
                        <select wire:model="presupuesto_id" class="form-input w-full @error('presupuesto_id') border-rose-500 @enderror">
                            <option value="">Seleccionar presupuesto...</option>
                            @foreach ($this->presupuestos as $presupuesto)
                                <option value="{{ $presupuesto->id }}">{{ $presupuesto->name }}</option>
                            @endforeach
                        </select>
                        @error('presupuesto_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    {{-- Campo Municipio Sede --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio Sede</label>
                        <select wire:model="municipio_id" class="form-input w-full @error('municipio_id') border-rose-500 @enderror">
                            <option value="">Ninguno</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                        @error('municipio_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Sección de Firmas --}}
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-3">Firmas de Documentos</p>

                        <div class="space-y-4">
                            {{-- Coordinador Administrativo --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre Coord. Administrativo</label>
                                <input type="text" wire:model="firma_nombre_coord_admin" class="form-input w-full @error('firma_nombre_coord_admin') border-rose-500 @enderror" />
                                @error('firma_nombre_coord_admin') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargo Coord. Administrativo</label>
                                <input type="text" wire:model="firma_cargo_admin" class="form-input w-full @error('firma_cargo_admin') border-rose-500 @enderror" />
                                @error('firma_cargo_admin') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>

                            {{-- Coordinador de Contrato --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre Coord. Contrato</label>
                                <input type="text" wire:model="firma_nombre_coord_contrato" class="form-input w-full @error('firma_nombre_coord_contrato') border-rose-500 @enderror" />
                                @error('firma_nombre_coord_contrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargo Coord. Contrato</label>
                                <input type="text" wire:model="firma_cargo_contrato" class="form-input w-full @error('firma_cargo_contrato') border-rose-500 @enderror" />
                                @error('firma_cargo_contrato') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                    <button type="button" wire:click="closeModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="save" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal de confirmación de eliminación (se muestra si $deleteModalOpen es true) --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal" wire:key="delete-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Regional</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $regionalToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
