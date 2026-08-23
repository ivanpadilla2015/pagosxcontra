<?php

use App\Models\Retencion;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar el CRUD de "Retenciones".
 *
 * Sigue la misma lógica que el componente de Régimen Tributario,
 * pero para el catálogo de Retenciones. Solo tiene el campo 'name'.
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
     * ID de la retención que se está editando.
     * null = modo "crear"; un entero = modo "editar".
     */
    public ?int $editingId = null;

    /** ID de la retención seleccionada para eliminar (guardado al confirmar). */
    public ?int $retencionToDeleteId = null;

    /** Nombre de la retención a eliminar, usado solo para mostrarlo en el modal. */
    public string $retencionToDeleteName = '';

    /** Campo del formulario: nombre de la retención. */
    public string $name = '';

    /** Indica si la retención se calcula sobre la base gravable. */
    public bool $aplica_base = false;

    /** Indica si la retención se calcula sobre el IVA. */
    public bool $aplica_iva = false;

    /** Tipo de retención: general, parafiscal o territorial. */
    public string $tipo = 'general';

    /** Divisor para el cálculo (100 = por ciento, 1000 = por mil). */
    public int $divisor = 100;

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    /**
     * Reglas de validación para los campos del formulario.
     * Livewire usa este método automáticamente al llamar a $this->validate().
     *
     * @return array Reglas aplicadas a 'name', 'aplica_base' y 'aplica_iva'.
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('retenciones', 'name')->ignore($this->editingId),
            ],
            'tipo' => ['required', 'string', 'in:general,parafiscal,territorial'],
            'aplica_base' => ['boolean'],
            'aplica_iva' => ['boolean'],
            'divisor' => ['required', 'integer', 'min:1'],
        ];
    }

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (datos derivados / consultas)
    // ---------------------------------------------------------------------

    /**
     * Devuelve la lista paginada de retenciones.
     *
     * - Filtra por $search en el campo 'name' (LIKE %...%).
     * - Ordena por los más recientes (latest = created_at desc).
     * - Pagina de 10 en 10.
     */
    #[Computed]
    public function retenciones()
    {
        return Retencion::query()
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
     * Se ejecuta automáticamente cuando cambia $search. Reinicia la
     * paginación para que la búsqueda empiece desde la página 1.
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
     * @param int|null $id ID de la retención a editar. Si es null, es modo crear.
     */
    public function openModal(?int $id = null): void
    {
        // Limpia errores de validación previos y los campos del formulario.
        $this->resetValidation();
        $this->reset(['name', 'tipo', 'aplica_base', 'aplica_iva', 'divisor', 'editingId']);
        $this->editingId = $id;

        // Si recibimos un ID, cargamos los datos de la retención a editar.
        if ($id) {
            $retencion = Retencion::findOrFail($id);
            $this->name = $retencion->name;
            $this->tipo = $retencion->tipo;
            $this->aplica_base = $retencion->aplica_base;
            $this->aplica_iva = $retencion->aplica_iva;
            $this->divisor = $retencion->divisor;
        }

        $this->modalOpen = true;
    }

    /** Cierra el modal de crear/editar y limpia el formulario y errores. */
    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset(['name', 'tipo', 'aplica_base', 'aplica_iva', 'divisor', 'editingId']);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ACCIONES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    /**
     * Prepara la eliminación mostrando el modal de confirmación.
     *
     * @param Retencion $retencion Modelo inyectado por Livewire.
     */
    public function confirmDelete(Retencion $retencion): void
    {
        $this->retencionToDeleteId = $retencion->id;
        $this->retencionToDeleteName = $retencion->name;
        $this->deleteModalOpen = true;
    }

    /** Cierra el modal de eliminación y limpia las variables temporales. */
    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->retencionToDeleteId = null;
        $this->retencionToDeleteName = '';
    }

    /**
     * Elimina definitivamente la retención seleccionada y muestra un mensaje.
     * Las asignaciones en los pivotes (regimen_retencion, proveedor_retencion)
     * se eliminan en cascada gracias a las claves foráneas.
     */
    public function delete(): void
    {
        Retencion::findOrFail($this->retencionToDeleteId)->delete();
        session()->flash('message', 'Retención eliminada correctamente.');
        $this->closeDeleteModal();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    /**
     * Valida el formulario y crea o actualiza la retención según corresponda.
     * Después cierra el modal y muestra un mensaje de éxito.
     */
    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            // Modo edición: actualiza el registro existente.
            Retencion::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Retención actualizada correctamente.');
        } else {
            // Modo creación: inserta un nuevo registro.
            Retencion::create($data);
            session()->flash('message', 'Retención creada correctamente.');
        }

        $this->closeModal();
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado: título de la página y botón "Nueva Retención" --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Retenciones</h1>
        {{-- Abre el modal en modo crear (sin ID) --}}
        <button type="button" wire:click="openModal()" class="btn bg-white hover:bg-gray-100 text-gray-800 border border-gray-200">Nueva Retención</button>
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

    {{-- Tabla con la lista paginada de retenciones (usa la propiedad computada $this->retenciones) --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-center">Divisor</th>
                    <th class="px-4 py-3 text-left">Base de cálculo</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                {{-- Recorre las retenciones; @empty muestra mensaje si no hay resultados --}}
                @forelse ($this->retenciones as $retencion)
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $retencion->name }}</td>
                        <td class="px-4 py-3">
                            @if ($retencion->tipo === 'general')
                                <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs px-2 py-0.5">General</span>
                            @elseif ($retencion->tipo === 'parafiscal')
                                <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs px-2 py-0.5">Parafiscal</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5">Territorial</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-100 text-center">{{ $retencion->divisor == 1000 ? 'Por mil' : 'Por ciento' }}</td>
                        <td class="px-4 py-3">
                            @if ($retencion->aplica_base)
                                <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 text-xs px-2 py-0.5 mr-1 mb-1">Base</span>
                            @endif
                            @if ($retencion->aplica_iva)
                                <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs px-2 py-0.5 mr-1 mb-1">IVA</span>
                            @endif
                            @unless ($retencion->aplica_base || $retencion->aplica_iva)
                                <span class="text-xs text-gray-400 dark:text-gray-500">Sin definir</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            {{-- Botón editar: abre el modal pasando el ID --}}
                            <button type="button" wire:click="openModal({{ $retencion->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            {{-- Botón eliminar: dispara el modal de confirmación --}}
                            <button type="button" wire:click="confirmDelete({{ $retencion->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay retenciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Controles de paginación --}}
    <div class="mt-4">
        {{ $this->retenciones->links() }}
    </div>

    {{-- Modal de crear/editar (se muestra solo si $modalOpen es true) --}}
    @if ($modalOpen)
        {{-- Clic fuera del modal lo cierra; wire:click.stop evita cerrar al pulsar dentro --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="retencion-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                {{-- Título cambia según modo crear/editar --}}
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar Retención' : 'Nueva Retención' }}</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
                        <input type="text" wire:model="name" class="form-input w-full @error('name') border-rose-500 @enderror" />
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                        <select wire:model="tipo" class="form-select w-full @error('tipo') border-rose-500 @enderror">
                            <option value="general">General (proveedor/régimen)</option>
                            <option value="parafiscal">Parafiscal (producto)</option>
                            <option value="territorial">Territorial (municipio/departamento)</option>
                        </select>
                        @error('tipo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Divisor (base de cálculo)</label>
                        <input type="number" wire:model.live="divisor" placeholder="100" class="form-input w-full @error('divisor') border-rose-500 @enderror" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">100 = por ciento, 1000 = por mil (ej: Reteica)</p>
                        @error('divisor') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    {{-- Base de cálculo: sobre qué se aplica la retención al deducir --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Base de cálculo</label>
                        <div class="space-y-2 rounded-lg border border-gray-200 dark:border-gray-700/60 p-3">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="aplica_base" class="form-checkbox" />
                                <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">Se aplica sobre la base gravable</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="aplica_iva" class="form-checkbox" />
                                <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">Se aplica sobre el IVA</span>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Define sobre qué valor se calcula la deducción al momento del pago.</p>
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
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Retención</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $retencionToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
