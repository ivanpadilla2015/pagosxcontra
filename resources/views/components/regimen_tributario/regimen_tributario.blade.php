<?php

use App\Models\RegimenTributario;
use App\Models\Retencion;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar el CRUD de "Regímenes Tributarios" (regimen_tributario).
 *
 * Sigue la misma lógica que el componente de Tipos de Persona (tipopers),
 * pero para la entidad RegimenTributario. Solo tiene el campo 'name'.
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
     * ID del régimen tributario que se está editando.
     * null = modo "crear"; un entero = modo "editar".
     */
    public ?int $editingId = null;

    /** ID del régimen tributario seleccionado para eliminar (guardado al confirmar). */
    public ?int $regimenTributarioToDeleteId = null;

    /** Nombre del régimen tributario a eliminar, usado solo para mostrarlo en el modal. */
    public string $regimenTributarioToDeleteName = '';

    /** Campo del formulario: nombre del régimen tributario. */
    public string $name = '';

    /**
     * IDs de las retenciones que aplica este régimen (mapeo editable).
     * Se enlaza a los checkboxes del formulario con wire:model.
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
     *
     * @return array Reglas aplicadas a 'name' y a las retenciones.
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'retencionesSeleccionadas' => ['array'],
            'retencionesSeleccionadas.*' => ['exists:retenciones,id'],
        ];
    }

    /**
     * Catálogo completo de retenciones para pintar los checkboxes.
     */
    #[Computed]
    public function retenciones()
    {
        return Retencion::orderBy('name')->get();
    }

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (datos derivados / consultas)
    // ---------------------------------------------------------------------

    /**
     * Devuelve la lista paginada de regímenes tributarios.
     * #[Computed] cachea el resultado durante el mismo render para no
     * ejecutar la consulta varias veces (se usa en la tabla y en los links).
     *
     * - Filtra por $search en el campo 'name' (LIKE %...%).
     * - Ordena por los más recientes (latest = created_at desc).
     * - Pagina de 10 en 10.
     */
    #[Computed]
    public function regimenTributarios()
    {
        return RegimenTributario::query()
            ->with('retenciones')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(10);
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
     * @param int|null $id ID del régimen tributario a editar. Si es null, es modo crear.
     */
    public function openModal(?int $id = null): void
    {
        // Limpia errores de validación previos y los campos del formulario.
        $this->resetValidation();
        $this->reset(['name', 'retencionesSeleccionadas', 'editingId']);
        $this->editingId = $id;

        // Si recibimos un ID, cargamos los datos del régimen tributario a editar.
        if ($id) {
            $regimenTributario = RegimenTributario::findOrFail($id);
            $this->name = $regimenTributario->name;
            $this->retencionesSeleccionadas = $regimenTributario->retenciones()->pluck('retenciones.id')->toArray();
        }

        $this->modalOpen = true;
    }

    /** Cierra el modal de crear/editar y limpia el formulario y errores. */
    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset(['name', 'retencionesSeleccionadas', 'editingId']);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    /**
     * Prepara la eliminación mostrando el modal de confirmación.
     * Livewire resuelve el modelo RegimenTributario automáticamente desde el ID
     * que se le pasa en el botón (wire:click="confirmDelete({{ $regimenTributario->id }})").
     *
     * @param RegimenTributario $regimenTributario Modelo inyectado por Livewire.
     */
    public function confirmDelete(RegimenTributario $regimenTributario): void
    {
        $this->regimenTributarioToDeleteId = $regimenTributario->id;
        $this->regimenTributarioToDeleteName = $regimenTributario->name;
        $this->deleteModalOpen = true;
    }

    /** Cierra el modal de eliminación y limpia las variables temporales. */
    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->regimenTributarioToDeleteId = null;
        $this->regimenTributarioToDeleteName = '';
    }

    /**
     * Elimina definitivamente el régimen tributario seleccionado y muestra un mensaje.
     * Usa findOrFail para lanzar 404 si el ID ya no existe.
     */
    public function delete(): void
    {
        RegimenTributario::findOrFail($this->regimenTributarioToDeleteId)->delete();
        session()->flash('message', 'Régimen tributario eliminado correctamente.');
        $this->closeDeleteModal();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    /**
     * Valida el formulario y crea o actualiza el régimen tributario según corresponda.
     * Después cierra el modal y muestra un mensaje de éxito.
     */
    public function save(): void
    {
        // Valida usando las reglas de rules(); si falla, Livewire detiene
        // la ejecución y muestra los errores en la vista.
        $this->validate();

        if ($this->editingId) {
            // Modo edición: actualiza el registro existente.
            $regimenTributario = RegimenTributario::findOrFail($this->editingId);
            $regimenTributario->update(['name' => $this->name]);
            session()->flash('message', 'Régimen tributario actualizado correctamente.');
        } else {
            // Modo creación: inserta un nuevo registro.
            $regimenTributario = RegimenTributario::create(['name' => $this->name]);
            session()->flash('message', 'Régimen tributario creado correctamente.');
        }

        // Sincroniza el mapeo régimen -> retenciones (tabla pivote regimen_retencion).
        $regimenTributario->retenciones()->sync($this->retencionesSeleccionadas);

        $this->closeModal();
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado: título de la página y botón "Nuevo Régimen Tributario" --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Regímenes Tributarios</h1>
        {{-- Abre el modal en modo crear (sin ID) --}}
        <button type="button" wire:click="openModal()" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Nuevo Régimen Tributario</button>
    </div>

    {{-- Mensaje de éxito temporal (flash) mostrado tras crear/editar/eliminar --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Campo de búsqueda: wire:model.live actualiza $search en tiempo real --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Buscar nombre..." class="form-input w-full max-w-xs" />
    </div>

    {{-- Tabla con la lista paginada de regímenes tributarios (usa la propiedad computada $this->regimenTributarios) --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Retenciones</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                {{-- Recorre los regímenes tributarios; @empty muestra mensaje si no hay resultados --}}
                @forelse ($this->regimenTributarios as $regimenTributario)
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $regimenTributario->name }}</td>
                        <td class="px-4 py-3">
                            @forelse ($regimenTributario->retenciones as $retencion)
                                <span class="inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 text-xs px-2 py-0.5 mr-1 mb-1">{{ $retencion->name }}</span>
                            @empty
                                <span class="text-xs text-gray-400 dark:text-gray-500">Ninguna</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            {{-- Botón editar: abre el modal pasando el ID --}}
                            <button type="button" wire:click="openModal({{ $regimenTributario->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            {{-- Botón eliminar: dispara el modal de confirmación --}}
                            <button type="button" wire:click="confirmDelete({{ $regimenTributario->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay regímenes tributarios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Controles de paginación --}}
    <div class="mt-4">
        {{ $this->regimenTributarios->links() }}
    </div>

    {{-- Modal de crear/editar (se muestra solo si $modalOpen es true) --}}
    @if ($modalOpen)
        {{-- Clic fuera del modal lo cierra; wire:click.stop evita cerrar al pulsar dentro --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="regimen-tributario-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                {{-- Título cambia según modo crear/editar --}}
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar Régimen Tributario' : 'Nuevo Régimen Tributario' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
                        <input type="text" wire:model="name" class="form-input w-full @error('name') border-rose-500 @enderror" />
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    {{-- Mapeo editable: retenciones que aplica este régimen --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Retenciones que aplica</label>
                        <div class="space-y-2 rounded-lg border border-gray-200 dark:border-gray-700/60 p-3">
                            @foreach ($this->retenciones as $retencion)
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="retencionesSeleccionadas" value="{{ $retencion->id }}" class="form-checkbox" />
                                    <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">{{ $retencion->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Estas retenciones se asignarán automáticamente a los proveedores con este régimen.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
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
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Régimen Tributario</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $regimenTributarioToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
