<?php

use App\Models\Producto;
use App\Models\Rubro;
use App\Models\Uso;
use App\Models\Retencion;
use App\Models\Municipio;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar Productos.
 *
 * Flujo de creación:
 *   1. Selecciona un rubro.
 *   2. Escribe el nombre del producto y elige el código de uso (filtrado por rubro).
 *   3. Se agrega a la tabla de productos, donde se puede editar o eliminar.
 */
new class extends Component
{
    use WithPagination;

    // ---------------------------------------------------------------------
    // PROPIEDADES DEL FORMULARIO
    // ---------------------------------------------------------------------

    /** Rubro seleccionado (filtra los usos disponibles). */
    #[Url]
    public ?int $rubro_id = null;

    /** Nombre del producto. */
    public string $name = '';

    /** Tipo del producto: bien o servicio. */
    public string $tipo = 'bien';

    /** Uso (código de uso) seleccionado para el producto. */
    public ?int $uso_id = null;

    /** ID del producto en edición (null = creando). */
    public ?int $editingId = null;

    /** Indica si el producto es agrícola (para retenciones parafiscales). */
    public bool $es_agricola = false;

    /** Municipio asociado al producto (solo servicios, para Reteica). */
    public ?int $municipio_id = null;

    /** IDs de retenciones parafiscales aplicables al producto. */
    public array $retencionesParafiscales = [];

    // ---------------------------------------------------------------------
    // PROPIEDADES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    public bool $deleteModalOpen = false;
    public ?int $productoToDeleteId = null;
    public string $productoToDeleteName = '';

    /** Buscador del listado de productos. */
    #[Url]
    public string $search = '';

    /** Regional del usuario autenticado. */
    #[Computed]
    public function userRegionalId()
    {
        return Auth::user()->regional_id;
    }

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    protected function rules(): array
    {
        return [
            'rubro_id' => ['required', 'exists:rubros,id'],
            'name' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:bien,servicio'],
            'uso_id' => ['required', 'exists:usos,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'es_agricola' => ['boolean'],
            'retencionesParafiscales' => ['array'],
            'retencionesParafiscales.*' => ['exists:retenciones,id'],
        ];
    }

    // ---------------------------------------------------------------------
    // DATOS PARA LOS SELECTS
    // ---------------------------------------------------------------------

    /** Lista de rubros para el primer select. */
    #[Computed]
    public function rubros()
    {
        return Rubro::orderBy('codigo_rubro')->get();
    }

    /** Usos del rubro seleccionado (para el segundo select). */
    #[Computed]
    public function usos()
    {
        if (! $this->rubro_id) {
            return collect();
        }

        return Uso::where('rubro_id', $this->rubro_id)
            ->orderBy('codigo_uso')
            ->get();
    }

    /** Catálogo de retenciones parafisales (para los checkboxes). */
    #[Computed]
    public function retenciones()
    {
        return Retencion::where('tipo', 'parafiscal')->orderBy('name')->get();
    }

    /** Municipios de la regional del usuario (para select de servicios). */
    #[Computed]
    public function municipios()
    {
        return Municipio::where('regional_id', $this->userRegionalId)
            ->orderBy('nombre')
            ->get();
    }

    /** Listado paginado de productos creados (filtrado por rubro y regional). */
    #[Computed]
    public function productos()
    {
        if (! $this->rubro_id) {
            return Producto::query()->whereRaw('0 = 1')->paginate(10);
        }

        return Producto::query()
            ->with(['rubro', 'uso'])
            ->where('rubro_id', $this->rubro_id)
            ->where('regional_id', $this->userRegionalId)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate(10);
    }

    // ---------------------------------------------------------------------
    // REACCIONES
    // ---------------------------------------------------------------------

    /**
     * Al cambiar de rubro, se limpia el uso seleccionado (los usos dependen del rubro).
     */
    public function updatedRubroId(): void
    {
        $this->uso_id = null;
        $this->resetPage();
    }

    public function seleccionarRubro($id): void
    {
        $this->rubro_id = $id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $producto = Producto::findOrFail($this->editingId);
            $producto->update($data);
            session()->flash('message', 'Producto actualizado correctamente.');
        } else {
            $data['regional_id'] = $this->userRegionalId;
            $producto = Producto::create($data);
            session()->flash('message', 'Producto creado correctamente.');
        }

        // Sincronizar retenciones parafiscales
        $producto->retencionesParafiscales()->sync($this->retencionesParafiscales);

        $this->resetForm();
    }

    /**
     * Carga un producto en el formulario para editarlo.
     */
    public function edit(int $id): void
    {
        $producto = Producto::findOrFail($id);
        $this->editingId = $producto->id;
        $this->rubro_id = $producto->rubro_id;
        $this->name = $producto->name;
        $this->tipo = $producto->tipo;
        $this->uso_id = $producto->uso_id;
        $this->municipio_id = $producto->municipio_id;
        $this->es_agricola = $producto->es_agricola;
        $this->retencionesParafiscales = $producto->retencionesParafiscales()->pluck('retenciones.id')->toArray();
        $this->resetValidation();
    }

    /** Cancela la edición y limpia el formulario (mantiene el rubro seleccionado). */
    public function resetForm(): void
    {
        $this->reset(['name', 'tipo', 'uso_id', 'municipio_id', 'es_agricola', 'retencionesParafiscales', 'editingId']);
        $this->tipo = 'bien';
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ELIMINAR
    // ---------------------------------------------------------------------

    public function confirmDelete(Producto $producto): void
    {
        $this->productoToDeleteId = $producto->id;
        $this->productoToDeleteName = $producto->name;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->productoToDeleteId = null;
        $this->productoToDeleteName = '';
    }

    public function delete(): void
    {
        Producto::findOrFail($this->productoToDeleteId)->delete();
        session()->flash('message', 'Producto eliminado correctamente.');
        $this->closeDeleteModal();
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Productos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Selecciona un rubro, luego define el producto y su código de uso.</p>
    </div>

    {{-- Mensaje de éxito --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Formulario de creación / edición --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar producto' : 'Nuevo producto' }}</h2>

        {{-- Paso 1: seleccionar rubro --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1. Rubro</label>
            <div class="relative" x-data="{ open: false, search: '{{ addslashes($rubro_id ? $this->rubros->firstWhere('id', $rubro_id)?->codigo_rubro . ' — ' . $this->rubros->firstWhere('id', $rubro_id)?->nombre_rubro : '') }}' }" @click.away="open = false" wire:ignore>
                <input type="text" x-model="search" @focus="open = true; search = ''" @keydown.escape="open = false" placeholder="Escriba para buscar rubro..." class="form-input w-full" autocomplete="off" />
                <div x-show="open" x-cloak class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                    @foreach ($this->rubros as $rubro)
                        <button type="button"
                            x-show="search === '' || '{{ addslashes(strtolower($rubro->codigo_rubro)) }}'.includes(search.toLowerCase()) || '{{ addslashes(strtolower($rubro->nombre_rubro)) }}'.includes(search.toLowerCase())"
                            wire:click="seleccionarRubro({{ $rubro->id }})"
                            x-on:click="search = '{{ addslashes($rubro->codigo_rubro) }} — {{ addslashes($rubro->nombre_rubro) }}'; open = false"
                            class="w-full text-left px-3 py-2 hover:bg-violet-50 dark:hover:bg-gray-600 text-sm border-b border-gray-100 dark:border-gray-600 last:border-0">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $rubro->codigo_rubro }}</span>
                            <span class="text-gray-500 dark:text-gray-400 ml-1">— {{ $rubro->nombre_rubro }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            @error('rubro_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
        </div>

        {{-- Paso 2: nombre del producto + código de uso (solo tras elegir rubro) --}}
        @if ($rubro_id)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">2. Nombre del producto</label>
                    <input type="text" wire:model="name" placeholder="Nombre del producto" class="form-input w-full @error('name') border-rose-500 @enderror" />
                    @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">3. Tipo *</label>
                    <select wire:model="tipo" class="form-input w-full @error('tipo') border-rose-500 @enderror">
                        <option value="bien">Bien</option>
                        <option value="servicio">Servicio</option>
                    </select>
                    @error('tipo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">4. Código de uso</label>
                    <select wire:model="uso_id" class="form-input w-full @error('uso_id') border-rose-500 @enderror">
                        <option value="">Seleccionar uso...</option>
                        @foreach ($this->usos as $uso)
                            <option value="{{ $uso->id }}">{{ $uso->codigo_uso }} — {{ $uso->nombre_uso }}</option>
                        @endforeach
                    </select>
                    @error('uso_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Municipio: solo visible para servicios (Reteica) --}}
            @if ($tipo === 'servicio')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">5. Municipio (para Reteica)</label>
                        <select wire:model="municipio_id" class="form-input w-full @error('municipio_id') border-rose-500 @enderror">
                            <option value="">Sin municipio (se pedirá al facturar)</option>
                            @foreach ($this->municipios as $municipio)
                                <option value="{{ $municipio->id }}">{{ $municipio->nombre }}</option>
                            @endforeach
                        </select>
                        @error('municipio_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Si se asigna, se auto-selecciona al facturar este producto.</p>
                    </div>
                </div>
            @endif

            <label for="es_agricola_check" class="mt-4 flex p-4 rounded-lg border-2 cursor-pointer transition-colors {{ $es_agricola ? 'border-amber-400 bg-amber-50 dark:border-amber-500/60 dark:bg-amber-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700/60 dark:bg-gray-900/30 hover:border-gray-300 dark:hover:border-gray-600' }}">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ $es_agricola ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-gray-200 dark:bg-gray-700/60' }}">
                        <svg class="w-5 h-5 {{ $es_agricola ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="es_agricola_check" wire:model="es_agricola" class="form-checkbox w-5 h-5 text-amber-500 border-amber-400 focus:ring-amber-400" />
                            <span class="font-semibold text-sm {{ $es_agricola ? 'text-amber-800 dark:text-amber-200' : 'text-gray-700 dark:text-gray-300' }}">Producto agrícola</span>
                        </div>
                        <p class="text-xs mt-0.5 {{ $es_agricola ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">Aplica retenciones parafiscales (Fedepapa, Asohofrucol)</p>
                    </div>
                </div>
            </label>

            <div x-show="$wire.es_agricola" x-cloak class="mt-3 rounded-lg border border-gray-200 dark:border-gray-700/60 p-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Retenciones parafiscales aplicables</label>
                    @forelse ($this->retenciones as $retencion)
                        <label class="flex items-center mb-1">
                            <input type="checkbox" wire:model="retencionesParafiscales" value="{{ $retencion->id }}" class="form-checkbox" />
                            <span class="text-sm ml-2 text-gray-700 dark:text-gray-300">{{ $retencion->name }}</span>
                        </label>
                    @empty
                        <span class="text-xs text-gray-400 dark:text-gray-500">No hay retenciones parafiscales definidas.</span>
                    @endforelse
                </div>

            <div class="mt-4 flex justify-end space-x-3">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                @endif
                <button type="button" wire:click="save" class="btn bg-violet-600 hover:bg-violet-700 text-white border border-violet-600">{{ $editingId ? 'Actualizar producto' : 'Agregar producto' }}</button>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Selecciona un rubro para continuar.</p>
        @endif
    </div>

    {{-- Listado de productos --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar producto por nombre..." class="form-input w-full max-w-xs" />
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Rubro</th>
                    <th class="px-4 py-3 text-left">Código de uso</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->productos as $producto)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $producto->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $producto->tipo === 'bien' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                {{ $producto->tipo === 'bien' ? 'Bien' : 'Servicio' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            @if ($producto->rubro)
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $producto->rubro->codigo_rubro }}</span>
                                <span class="block">{{ $producto->rubro->nombre_rubro }}</span>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            @if ($producto->uso)
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $producto->uso->codigo_uso }}</span>
                                <span class="block">{{ $producto->uso->nombre_uso }}</span>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $producto->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $producto->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            @if ($rubro_id)
                                No hay productos para este rubro.
                            @else
                                Selecciona un rubro para ver sus productos.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->productos->links() }}
    </div>

    {{-- Modal de confirmación de eliminación --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal" wire:key="delete-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-rose-100 dark:bg-rose-900/30">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Producto</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $productoToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
