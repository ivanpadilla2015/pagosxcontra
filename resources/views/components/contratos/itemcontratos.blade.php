<?php

use App\Models\Contrato;
use App\Models\Itemcontrato;
use App\Models\Producto;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para gestionar los ítems de un contrato.
 *
 * Flujo:
 *   1. El usuario escribe el número del contrato y pulsa "Buscar".
 *   2. Si el contrato existe, se muestra el proveedor asociado.
 *   3. Se selecciona un producto (del modelo Producto) y se ingresan los
 *      valores: sin IVA, IVA (%), valor del IVA y valor con IVA, más la unidad.
 *   4. Al crear, el ítem aparece en la tabla inferior con acciones editar/eliminar.
 */
new class extends Component
{
    use WithPagination;

    // ---------------------------------------------------------------------
    // BÚSQUEDA DEL CONTRATO
    // ---------------------------------------------------------------------

    /** Número del contrato escrito por el usuario. */
    public string $numcontrato = '';

    /** Contrato encontrado (null si no se ha buscado o no existe). */
    public ?Contrato $contrato = null;

    /** Mensaje de error cuando el contrato no existe. */
    public ?string $contratoError = null;

    // ---------------------------------------------------------------------
    // PROPIEDADES DEL FORMULARIO
    // ---------------------------------------------------------------------

    public ?int $producto_id = null;
    public ?float $valorprosiniva = null;
    public ?float $iva = null;
    public ?float $valoriva = null;
    public ?float $valorproconiva = null;
    public string $unidad = '';

    /** ID del ítem en edición (null = creando). */
    public ?int $editingId = null;

    // ---------------------------------------------------------------------
    // PROPIEDADES DEL MODAL ELIMINAR
    // ---------------------------------------------------------------------

    public bool $deleteModalOpen = false;
    public ?int $itemToDeleteId = null;
    public string $itemToDeleteName = '';

    /** Buscador del listado de ítems. */
    #[Url]
    public string $search = '';

    // ---------------------------------------------------------------------
    // VALIDACIÓN
    // ---------------------------------------------------------------------

    protected function rules(): array
    {
        return [
            'producto_id' => ['required', 'exists:productos,id'],
            'valorprosiniva' => ['required', 'numeric', 'min:0'],
            'iva' => ['required', 'numeric', 'min:0', 'max:100'],
            'valoriva' => ['required', 'numeric', 'min:0'],
            'valorproconiva' => ['required', 'numeric', 'min:0'],
            'unidad' => ['required', 'string', 'max:8'],
        ];
    }

    // ---------------------------------------------------------------------
    // DATOS PARA LOS SELECTS Y LISTADOS
    // ---------------------------------------------------------------------

    /** Productos disponibles (creados en el modelo Producto). */
    #[Computed]
    public function productos()
    {
        return Producto::orderBy('name')->get();
    }

    /** Ítems del contrato encontrado. */
    #[Computed]
    public function items()
    {
        if (! $this->contrato) {
            return collect();
        }

        return Itemcontrato::query()
            ->with('producto')
            ->where('contrato_id', $this->contrato->id)
            ->when($this->search, function ($query) {
                $query->whereHas('producto', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);
    }

    // ---------------------------------------------------------------------
    // REACCIONES
    // ---------------------------------------------------------------------

    /**
     * Busca el contrato por número. Si existe, muestra su proveedor;
     * de lo contrario, muestra un mensaje de error y limpia el formulario.
     */
    public function buscarContrato(): void
    {
        $this->reset(['contrato', 'contratoError', 'producto_id', 'valorprosiniva', 'iva', 'valoriva', 'valorproconiva', 'unidad', 'editingId']);
        $this->resetValidation();

        $numero = trim($this->numcontrato);

        if ($numero === '') {
            $this->contratoError = 'Ingresa el número del contrato.';
            return;
        }

        $contrato = Contrato::with('proveedor')->where('numcontrato', $numero)->first();

        if (! $contrato) {
            $this->contratoError = 'No se encontró un contrato con el número '.$numero.'.';
            return;
        }

        $this->contrato = $contrato;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Recalcula el valor del IVA y el valor con IVA al cambiar el valor sin
     * IVA o el porcentaje de IVA.
     */
    public function recalcular(): void
    {
        $base = floatval($this->valorprosiniva ?? 0);
        $porcentaje = floatval($this->iva ?? 0);

        $this->valoriva = round($base * $porcentaje / 100, 2);
        $this->valorproconiva = round($base + $this->valoriva, 2);
    }

    public function updatedValorprosiniva(): void
    {
        $this->recalcular();
    }

    public function updatedIva(): void
    {
        $this->recalcular();
    }

    // ---------------------------------------------------------------------
    // GUARDAR (CREAR O ACTUALIZAR)
    // ---------------------------------------------------------------------

    public function save(): void
    {
        if (! $this->contrato) {
            $this->contratoError = 'Debes buscar y seleccionar un contrato válido.';
            return;
        }

        $data = $this->validate();
        $data['contrato_id'] = $this->contrato->id;

        if ($this->editingId) {
            Itemcontrato::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Ítem de contrato actualizado correctamente.');
        } else {
            Itemcontrato::create($data);
            session()->flash('message', 'Ítem de contrato creado correctamente.');
        }

        $this->resetForm();
    }

    /**
     * Carga un ítem en el formulario para editarlo.
     */
    public function edit(int $id): void
    {
        $item = Itemcontrato::findOrFail($id);
        $this->editingId = $item->id;
        $this->producto_id = $item->producto_id;
        $this->valorprosiniva = $item->valorprosiniva;
        $this->iva = $item->iva;
        $this->valoriva = $item->valoriva;
        $this->valorproconiva = $item->valorproconiva;
        $this->unidad = $item->unidad;
        $this->resetValidation();
    }

    /** Cancela la edición y limpia el formulario. */
    public function resetForm(): void
    {
        $this->reset(['producto_id', 'valorprosiniva', 'iva', 'valoriva', 'valorproconiva', 'unidad', 'editingId']);
        $this->resetValidation();
    }

    // ---------------------------------------------------------------------
    // ELIMINAR
    // ---------------------------------------------------------------------

    public function confirmDelete(Itemcontrato $item): void
    {
        $this->itemToDeleteId = $item->id;
        $this->itemToDeleteName = $item->producto?->name ?? 'Ítem';
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
        session()->flash('message', 'Ítem de contrato eliminado correctamente.');
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
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Ítems de Contrato</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Busca un contrato por su número para gestionar sus productos/ítems.</p>
    </div>

    {{-- Mensaje de éxito --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Paso 1: buscar contrato por número --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">1. Buscar contrato</h2>

        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de contrato</label>
                <input type="text" wire:model="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: CT-2026-001" class="form-input w-full @if($contratoError) border-rose-500 @endif" />
            </div>
            <button type="button" wire:click="buscarContrato" class="btn bg-violet-600 hover:bg-violet-700 text-white border border-violet-600">Buscar</button>
        </div>

        @if ($contratoError)
            <p class="mt-2 text-sm text-rose-500">{{ $contratoError }}</p>
        @endif

        @if ($contrato)
            <div class="mt-4 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 dark:border-violet-700/60 dark:bg-violet-900/20">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Contrato: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</span>
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Proveedor: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '—' }}</span>
                </p>
            </div>
        @endif
    </div>

    {{-- Paso 2: formulario de ítem (solo si el contrato existe) --}}
    @if ($contrato)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar ítem' : '2. Nuevo ítem' }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Producto</label>
                    <div
                        x-data="{
                            open: false,
                            search: '',
                            productoId: $wire.entangle('producto_id'),
                            productos: @js($this->productos->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->all()),
                            get filtered() {
                                if (this.search === '') return this.productos;
                                return this.productos.filter(p => p.name.toLowerCase().includes(this.search.toLowerCase()));
                            },
                            selectedName() {
                                const p = this.productos.find(p => p.id == this.productoId);
                                return p ? p.name : 'Seleccionar producto...';
                            }
                        }"
                        @click.outside="open = false"
                        class="relative"
                    >
                        <button type="button" @click="open = !open" class="form-input w-full flex items-center justify-between text-left @error('producto_id') border-rose-500 @enderror">
                            <span :class="productoId ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'" x-text="selectedName()"></span>
                            <svg class="w-4 h-4 shrink-0 ml-2 fill-current text-gray-400" viewBox="0 0 12 12"><path d="M5.9 11.4L.5 6l1.4-1.4 4 4 4-4L11.3 6z" /></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 shadow-lg">
                            <input type="text" x-model="search" placeholder="Buscar producto..." class="form-input w-full rounded-b-none border-0 border-b border-gray-200 dark:border-gray-700/60 focus:ring-0" @keydown.escape="open = false" />
                            <ul class="max-h-48 overflow-auto py-1">
                                <template x-for="p in filtered" :key="p.id">
                                    <li>
                                        <button type="button" @click="productoId = p.id; open = false; search = ''" class="block w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-violet-50 dark:hover:bg-violet-900/20" x-text="p.name"></button>
                                    </li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-3 py-2 text-sm text-gray-400 dark:text-gray-500">Sin resultados</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    @error('producto_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Unidad</label>
                    <input type="text" wire:model="unidad" maxlength="8" placeholder="Ej: UND, KG, M" class="form-input w-full @error('unidad') border-rose-500 @enderror" />
                    @error('unidad') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor sin IVA</label>
                    <input type="number" step="0.01" wire:model.live="valorprosiniva" placeholder="0.00" class="form-input w-full @error('valorprosiniva') border-rose-500 @enderror" />
                    @error('valorprosiniva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IVA (%)</label>
                    <input type="number" step="0.01" wire:model.live="iva" placeholder="Ej: 19" class="form-input w-full @error('iva') border-rose-500 @enderror" />
                    @error('iva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor del IVA</label>
                    <input type="number" step="0.01" wire:model="valoriva" placeholder="0.00" class="form-input w-full @error('valoriva') border-rose-500 @enderror" />
                    @error('valoriva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor con IVA</label>
                    <input type="number" step="0.01" wire:model="valorproconiva" placeholder="0.00" class="form-input w-full @error('valorproconiva') border-rose-500 @enderror" />
                    @error('valorproconiva') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4 flex justify-end space-x-3">
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                @endif
                <button type="button" wire:click="save" class="btn bg-violet-600 hover:bg-violet-700 text-white border border-violet-600">{{ $editingId ? 'Actualizar ítem' : 'Crear ítem' }}</button>
            </div>
        </div>

        {{-- Listado de ítems del contrato --}}
        <div class="mb-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar ítem por producto..." class="form-input w-full max-w-xs" />
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <table class="table-auto w-full">
                <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Producto</th>
                        <th class="px-4 py-3 text-left">Unidad</th>
                        <th class="px-4 py-3 text-right">Valor sin IVA</th>
                        <th class="px-4 py-3 text-right">IVA</th>
                        <th class="px-4 py-3 text-right">Valor IVA</th>
                        <th class="px-4 py-3 text-right">Valor con IVA</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->items as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $item->producto->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $item->unidad }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item->valorprosiniva, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item->iva, 2) }}%</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item->valoriva, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item->valorproconiva, 2) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $item->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                                </button>
                                <button type="button" wire:click="confirmDelete({{ $item->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                    <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay ítems registrados para este contrato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->items->links() }}
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
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Ítem</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar el ítem <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $itemToDeleteName }}</span>? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
