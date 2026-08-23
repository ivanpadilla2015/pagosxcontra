<?php

use Livewire\Component;
use App\Models\PlantillaDocumento;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $filterTipo = '';
    public $sortField = 'orden';
    public $sortDirection = 'asc';

    public $plantilla_documento_id;
    public $tipo = 'soporte';
    public $nombre_documento = '';
    public $orden = 0;

    public $confirmDeleteId = null;
    public $editing = false;
    public $showFormModal = false;
    public $showDeleteModal = false;

    #[Computed]
    public function plantillaDocumentos()
    {
        $query = PlantillaDocumento::query();

        if ($this->filterTipo !== '') {
            $query->where('tipo', $this->filterTipo);
        }

        if ($this->search !== '') {
            $query->where('nombre_documento', 'like', '%' . $this->search . '%');
        }

        return $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);
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

    public function updatingFilterTipo()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->resetInputFields();
        $this->editing = false;
        $maxOrden = PlantillaDocumento::where('tipo', $this->tipo)->max('orden') ?? 0;
        $this->orden = $maxOrden + 1;
        $this->showFormModal = true;
    }

    public function store()
    {
        $this->validate([
            'tipo' => 'required|in:soporte,expediente',
            'nombre_documento' => 'required|string|max:255',
            'orden' => 'required|integer|min:1',
        ]);

        PlantillaDocumento::create($this->getFormData());

        session()->flash('message', 'Documento plantilla creado exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function edit($id)
    {
        $doc = PlantillaDocumento::findOrFail($id);
        $this->plantilla_documento_id = $doc->id;
        $this->tipo = $doc->tipo;
        $this->nombre_documento = $doc->nombre_documento;
        $this->orden = $doc->orden;

        $this->editing = true;
        $this->showFormModal = true;
    }

    public function update()
    {
        $this->validate([
            'tipo' => 'required|in:soporte,expediente',
            'nombre_documento' => 'required|string|max:255',
            'orden' => 'required|integer|min:1',
        ]);

        $doc = PlantillaDocumento::findOrFail($this->plantilla_documento_id);
        $doc->update($this->getFormData());

        session()->flash('message', 'Documento plantilla actualizado exitosamente.');
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
        PlantillaDocumento::findOrFail($this->confirmDeleteId)->delete();
        session()->flash('message', 'Documento plantilla eliminado exitosamente.');
        $this->confirmDeleteId = null;
        $this->showDeleteModal = false;
    }

    public function moveUp($id)
    {
        $doc = PlantillaDocumento::findOrFail($id);
        $anterior = PlantillaDocumento::where('tipo', $doc->tipo)
            ->where('orden', '<', $doc->orden)
            ->orderBy('orden', 'desc')
            ->first();

        if ($anterior) {
            $tempOrden = $doc->orden;
            $doc->update(['orden' => $anterior->orden]);
            $anterior->update(['orden' => $tempOrden]);
        }
    }

    public function moveDown($id)
    {
        $doc = PlantillaDocumento::findOrFail($id);
        $siguiente = PlantillaDocumento::where('tipo', $doc->tipo)
            ->where('orden', '>', $doc->orden)
            ->orderBy('orden', 'asc')
            ->first();

        if ($siguiente) {
            $tempOrden = $doc->orden;
            $doc->update(['orden' => $siguiente->orden]);
            $siguiente->update(['orden' => $tempOrden]);
        }
    }

    public function updatedTipo($value)
    {
        if (! $this->editing) {
            $maxOrden = PlantillaDocumento::where('tipo', $value)->max('orden') ?? 0;
            $this->orden = $maxOrden + 1;
        }
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

    private function getFormData(): array
    {
        return [
            'tipo' => $this->tipo,
            'nombre_documento' => $this->nombre_documento,
            'orden' => $this->orden,
        ];
    }

    private function resetInputFields(): void
    {
        $this->reset([
            'plantilla_documento_id',
            'tipo',
            'nombre_documento',
            'orden',
        ]);
        $this->tipo = 'soporte';
        $this->nombre_documento = '';
        $maxOrden = PlantillaDocumento::where('tipo', $this->tipo)->max('orden') ?? 0;
        $this->orden = $maxOrden + 1;
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Documentos Plantilla</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de documentos soporte y expediente para trámites de pago</p>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row gap-3">
                    <x-input type="text" wire:model.live="search" placeholder="Buscar documento..." class="w-full sm:w-64" />
                    <select wire:model.live="filterTipo" class="form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                        <option value="">Todos los tipos</option>
                        <option value="soporte">Soporte</option>
                        <option value="expediente">Expediente</option>
                    </select>
                </div>
                <x-button wire:click="create">
                    <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 16 16">
                        <path d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                    </svg>
                    <span class="ml-2">Nuevo Documento</span>
                </x-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th wire:click="sortBy('orden')" class="cursor-pointer px-6 py-4 text-left w-16">
                                # 
                                @if ($sortField === 'orden')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th wire:click="sortBy('tipo')" class="cursor-pointer px-6 py-4 text-left w-32">
                                Tipo
                                @if ($sortField === 'tipo')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th wire:click="sortBy('nombre_documento')" class="cursor-pointer px-6 py-4 text-left">
                                Nombre Documento
                                @if ($sortField === 'nombre_documento')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-center w-40">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->plantillaDocumentos as $doc)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">{{ $doc->orden }}°</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $doc->tipo === 'soporte' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                                        {{ ucfirst($doc->tipo) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">{{ $doc->nombre_documento }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button wire:click="moveUp({{ $doc->id }})" class="inline-flex items-center px-1.5 py-1 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" title="Subir">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button wire:click="moveDown({{ $doc->id }})" class="inline-flex items-center px-1.5 py-1 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" title="Bajar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <button wire:click="edit({{ $doc->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 transition" title="Editar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $doc->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Eliminar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron documentos plantilla.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->plantillaDocumentos->links() }}
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
                class="mb-6 w-full max-w-lg mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="px-6 py-4">
                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editing ? 'Editar Documento Plantilla' : 'Nuevo Documento Plantilla' }}
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo <span class="text-red-500">*</span></label>
                            <select wire:model.live="tipo" class="w-full form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                <option value="soporte">Soporte</option>
                                <option value="expediente">Expediente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre Documento <span class="text-red-500">*</span></label>
                            <x-input type="text" wire:model="nombre_documento" class="w-full" placeholder="Nombre del documento" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Posición en el formulario <span class="text-red-500">*</span></label>
                            <x-input type="number" wire:model="orden" class="w-full" min="1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Número que define el orden en que aparece el documento al crear un trámite de pago.</p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-900/20 text-right gap-2">
                    <x-secondary-button wire:click="closeModal" class="mr-2">
                        Cancelar
                    </x-secondary-button>
                    <x-button wire:click="{{ $editing ? 'update' : 'store' }}">
                        {{ $editing ? 'Actualizar' : 'Guardar' }}
                    </x-button>
                </div>
            </div>
        </div>

        {{-- Modal Eliminar --}}
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
                class="mb-6 w-full max-w-lg mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="px-6 py-4">
                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Eliminar Documento Plantilla
                    </div>
                    <div class="mt-4 text-gray-600 dark:text-gray-400">
                        ¿Está seguro de que desea eliminar este documento plantilla? Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-900/20 text-right">
                    <x-secondary-button wire:click="closeModal" class="mr-2">
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
