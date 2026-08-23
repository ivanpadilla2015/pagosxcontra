<?php

use Livewire\Component;
use App\Models\Tiporegistro;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'desc';

    // Modal states
    public $tiporegistro_id;
    public $showFormModal = false;
    public $showDeleteModal = false;
    public $confirmDeleteId = null;
    public $editing = false;

    // Tiporegistro fields
    public $name = '';

    #[Computed]
    public function tiporegistros()
    {
        return Tiporegistro::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
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
        $this->showFormModal = true;
    }

    public function edit($id)
    {
        $this->resetInputFields();
        $tiporegistro = Tiporegistro::findOrFail($id);
        $this->tiporegistro_id = $tiporegistro->id;
        $this->name = $tiporegistro->name;
        $this->editing = true;
        $this->showFormModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:191',
        ]);

        if ($this->editing) {
            $tiporegistro = Tiporegistro::findOrFail($this->tiporegistro_id);
            $tiporegistro->update([
                'name' => $this->name,
            ]);
            session()->flash('message', 'Tipo de Registro actualizado correctamente.');
        } else {
            Tiporegistro::create([
                'name' => $this->name,
            ]);
            session()->flash('message', 'Tipo de Registro creado correctamente.');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $this->confirmDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        $tiporegistro = Tiporegistro::findOrFail($this->confirmDeleteId);
        $tiporegistro->delete();
        session()->flash('message', 'Tipo de Registro eliminado correctamente.');
        $this->showDeleteModal = false;
        $this->confirmDeleteId = null;
    }

    public function closeModal()
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    private function resetInputFields(): void
    {
        $this->reset();
        $this->name = '';
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Tipo Registros</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestión de tipos de registros</p>
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
                    <x-input type="text" wire:model.live="search" placeholder="Buscar por nombre..." />
                </div>
                <x-button wire:click="create">
                    <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 16 16">
                        <path d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                    </svg>
                    <span class="ml-2">Crear Nuevo Tipo</span>
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
                            <th wire:click="sortBy('name')" class="cursor-pointer px-6 py-4 text-left">
                                Nombre
                                @if ($sortField === 'name')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->tiporegistros as $tiporegistro)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">{{ $tiporegistro->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">
                                    {{ $tiporegistro->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button wire:click="edit({{ $tiporegistro->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 transition" title="Editar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        <button wire:click="confirmDelete({{ $tiporegistro->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Eliminar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron tipos de registros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->tiporegistros->links() }}
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
                        {{ $editing ? 'Editar Tipo de Registro' : 'Crear Nuevo Tipo de Registro' }}
                    </div>

                    <div class="space-y-4">
                        <div>
                            <x-label for="name" value="Nombre *" />
                            <x-input id="name" type="text" wire:model="name" class="w-full" placeholder="Ingrese el nombre del tipo..." />
                            <x-input-error for="name" />
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex justify-end gap-3">
                    <x-button wire:click="closeModal" class="bg-gray-500 hover:bg-gray-600">
                        Cancelar
                    </x-button>
                    <x-button wire:click="save">
                        {{ $editing ? 'Actualizar' : 'Guardar' }}
                    </x-button>
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
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Confirmar Eliminación</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">¿Está seguro que desea eliminar este tipo de registro? Esta acción no se puede deshacer.</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex justify-end gap-3">
                    <x-button wire:click="closeModal" class="bg-gray-500 hover:bg-gray-600">
                        Cancelar
                    </x-button>
                    <x-button wire:click="delete" class="bg-red-600 hover:bg-red-700">
                        Eliminar
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>
