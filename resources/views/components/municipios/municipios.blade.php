<?php

use App\Models\Municipio;
use App\Models\Regional;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $nombre = '';
    public string $codigo_dane = '';
    public string $departamento = '';
    public ?int $regional_id = null;
    public ?int $editingId = null;

    public bool $deleteModalOpen = false;
    public ?int $municipioToDeleteId = null;
    public string $municipioToDeleteName = '';

    #[Url]
    public string $search = '';

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo_dane' => ['nullable', 'string', 'max:10'],
            'departamento' => ['required', 'string', 'max:255'],
            'regional_id' => ['nullable', 'exists:regionals,id'],
        ];
    }

    #[Computed]
    public function municipios()
    {
        return Municipio::query()
            ->with('regional')
            ->when($this->search, function ($q) {
                $q->where('nombre', 'like', '%'.$this->search.'%')
                  ->orWhere('departamento', 'like', '%'.$this->search.'%')
                  ->orWhere('codigo_dane', 'like', '%'.$this->search.'%')
                  ->orWhereHas('regional', function ($rq) {
                      $rq->where('name', 'like', '%'.$this->search.'%');
                  });
            })
            ->orderBy('departamento')
            ->orderBy('nombre')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Municipio::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Municipio actualizado correctamente.');
        } else {
            Municipio::create($data);
            session()->flash('message', 'Municipio creado correctamente.');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $municipio = Municipio::findOrFail($id);
        $this->editingId = $municipio->id;
        $this->nombre = $municipio->nombre;
        $this->codigo_dane = $municipio->codigo_dane;
        $this->departamento = $municipio->departamento;
        $this->regional_id = $municipio->regional_id;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['nombre', 'codigo_dane', 'departamento', 'regional_id', 'editingId']);
        $this->resetValidation();
    }

    public function confirmDelete(Municipio $municipio): void
    {
        $this->municipioToDeleteId = $municipio->id;
        $this->municipioToDeleteName = $municipio->nombre;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->municipioToDeleteId = null;
        $this->municipioToDeleteName = '';
    }

    public function delete(): void
    {
        Municipio::findOrFail($this->municipioToDeleteId)->delete();
        session()->flash('message', 'Municipio eliminado correctamente.');
        $this->closeDeleteModal();
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Municipios</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestione los municipios para Reteica y estampillas.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar municipio' : 'Nuevo municipio' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" wire:model="nombre" placeholder="Ej: Santa Marta" class="form-input w-full @error('nombre') border-rose-500 @enderror" />
                @error('nombre') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código DANE</label>
                <input type="text" wire:model="codigo_dane" placeholder="Ej: 88001" class="form-input w-full @error('codigo_dane') border-rose-500 @enderror" />
                @error('codigo_dane') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Departamento *</label>
                <input type="text" wire:model="departamento" placeholder="Ej: Magdalena" class="form-input w-full @error('departamento') border-rose-500 @enderror" />
                @error('departamento') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regional</label>
                <select wire:model="regional_id" class="form-input w-full @error('regional_id') border-rose-500 @enderror">
                    <option value="">Ninguna</option>
                    @foreach(Regional::orderBy('name')->get() as $regional)
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
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre, departamento o regional..." class="form-input w-full max-w-xs" />
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Código DANE</th>
                    <th class="px-4 py-3 text-left">Departamento</th>
                    <th class="px-4 py-3 text-left">Regional</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->municipios as $municipio)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $municipio->nombre }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $municipio->codigo_dane ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $municipio->departamento }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $municipio->regional->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $municipio->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $municipio->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay municipios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->municipios->links() }}</div>

    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Municipio</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de que deseas eliminar <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $municipioToDeleteName }}</span>?</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
