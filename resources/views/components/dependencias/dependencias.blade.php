<?php

use App\Models\Dependencia;
use App\Models\Municipio;
use App\Models\Regional;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $name = '';
    public ?string $direccion = '';
    public ?int $municipio_id = null;
    public ?int $regional_id = null;
    public ?int $editingId = null;

    public bool $deleteModalOpen = false;
    public ?int $dependenciaToDeleteId = null;
    public string $dependenciaToDeleteName = '';

    #[Url]
    public string $search = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'regional_id' => ['nullable', 'exists:regionals,id'],
        ];
    }

    #[Computed]
    public function dependencias()
    {
        return Dependencia::query()
            ->with(['municipio', 'regional'])
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('nombre')->get();
    }

    #[Computed]
    public function regionales()
    {
        return Regional::orderBy('name')->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['municipio_id'] = $data['municipio_id'] ?: null;
        $data['regional_id'] = $data['regional_id'] ?: null;

        if ($this->editingId) {
            Dependencia::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Dependencia actualizada correctamente.');
        } else {
            Dependencia::create($data);
            session()->flash('message', 'Dependencia creada correctamente.');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $dependencia = Dependencia::findOrFail($id);
        $this->editingId = $dependencia->id;
        $this->name = $dependencia->name;
        $this->direccion = $dependencia->direccion;
        $this->municipio_id = $dependencia->municipio_id;
        $this->regional_id = $dependencia->regional_id;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'direccion', 'municipio_id', 'regional_id', 'editingId']);
        $this->resetValidation();
    }

    public function confirmDelete(Dependencia $dependencia): void
    {
        $this->dependenciaToDeleteId = $dependencia->id;
        $this->dependenciaToDeleteName = $dependencia->name;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->dependenciaToDeleteId = null;
        $this->dependenciaToDeleteName = '';
    }

    public function delete(): void
    {
        Dependencia::findOrFail($this->dependenciaToDeleteId)->delete();
        session()->flash('message', 'Dependencia eliminada correctamente.');
        $this->closeDeleteModal();
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Dependencias / Comedores</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestione las dependencias o comedores del sistema.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar dependencia' : 'Nueva dependencia' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" wire:model="name" placeholder="Ej: Comedor Central" class="form-input w-full @error('name') border-rose-500 @enderror" />
                @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                <input type="text" wire:model="direccion" placeholder="Ej: Cra 11 #12-05" class="form-input w-full @error('direccion') border-rose-500 @enderror" />
                @error('direccion') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                <select wire:model="municipio_id" class="form-input w-full">
                    <option value="">-- Seleccione --</option>
                    @foreach ($this->municipios as $municipio)
                        <option value="{{ $municipio->id }}">{{ $municipio->nombre }} ({{ $municipio->departamento }})</option>
                    @endforeach
                </select>
                @error('municipio_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regional</label>
                <select wire:model="regional_id" class="form-input w-full">
                    <option value="">-- Seleccione --</option>
                    @foreach ($this->regionales as $regional)
                        <option value="{{ $regional->id }}">{{ $regional->name }}</option>
                    @endforeach
                </select>
                @error('regional_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 flex justify-end space-x-3">
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
            @endif
            <button type="button" wire:click="save" class="btn bg-violet-600 hover:bg-violet-700 text-white border border-violet-600">{{ $editingId ? 'Actualizar' : 'Crear' }}</button>
        </div>
    </div>

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre..." class="form-input w-full max-w-xs" />
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Dirección</th>
                    <th class="px-4 py-3 text-left">Municipio</th>
                    <th class="px-4 py-3 text-left">Regional</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->dependencias as $dependencia)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $dependencia->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $dependencia->direccion ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $dependencia->municipio ? $dependencia->municipio->nombre.', '.$dependencia->municipio->departamento : '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $dependencia->regional->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $dependencia->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $dependencia->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay dependencias registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->dependencias->links() }}</div>

    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Dependencia</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $dependenciaToDeleteName }}</span>?</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
