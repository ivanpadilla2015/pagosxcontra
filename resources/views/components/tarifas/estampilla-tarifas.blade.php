<?php

use App\Models\EstampillaTarifa;
use App\Models\Retencion;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $retencion_id = null;
    public string $departamento = '';
    public ?string $tipo_adquisicion = null;
    public string $porcentaje = '';
    public ?int $editingId = null;

    public bool $deleteModalOpen = false;
    public ?int $deleteId = null;

    #[Url]
    public string $search = '';

    protected function rules(): array
    {
        return [
            'retencion_id' => ['required', 'exists:retenciones,id'],
            'departamento' => ['required', 'string', 'max:255'],
            'tipo_adquisicion' => ['nullable', 'string', 'in:bien,servicio'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    #[Computed]
    public function retencionesTerritoriales()
    {
        return Retencion::where('tipo', 'territorial')->orderBy('name')->get();
    }

    #[Computed]
    public function tarifas()
    {
        return EstampillaTarifa::with('retencion')
            ->when($this->search, function ($q) {
                $q->where('departamento', 'like', '%'.$this->search.'%')
                  ->orWhereHas('retencion', fn ($q2) => $q2->where('name', 'like', '%'.$this->search.'%'));
            })
            ->orderBy('departamento')
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
            EstampillaTarifa::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Tarifa actualizada correctamente.');
        } else {
            EstampillaTarifa::create($data);
            session()->flash('message', 'Tarifa creada correctamente.');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $tarifa = EstampillaTarifa::findOrFail($id);
        $this->editingId = $tarifa->id;
        $this->retencion_id = $tarifa->retencion_id;
        $this->departamento = $tarifa->departamento;
        $this->tipo_adquisicion = $tarifa->tipo_adquisicion;
        $this->porcentaje = $tarifa->porcentaje;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->reset(['retencion_id', 'departamento', 'tipo_adquisicion', 'porcentaje', 'editingId']);
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->deleteId = null;
    }

    public function delete(): void
    {
        EstampillaTarifa::findOrFail($this->deleteId)->delete();
        session()->flash('message', 'Tarifa eliminada correctamente.');
        $this->closeDeleteModal();
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Tarifas Estampillas</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestione las tarifas de estampillas territoriales por departamento.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editingId ? 'Editar tarifa' : 'Nueva tarifa' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retención *</label>
                <select wire:model="retencion_id" class="form-input w-full @error('retencion_id') border-rose-500 @enderror">
                    <option value="">Seleccionar...</option>
                    @foreach ($this->retencionesTerritoriales as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </select>
                @error('retencion_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Departamento *</label>
                <input type="text" wire:model="departamento" placeholder="Ej: Magdalena" class="form-input w-full @error('departamento') border-rose-500 @enderror" />
                @error('departamento') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo Adquisición</label>
                <select wire:model="tipo_adquisicion" class="form-input w-full">
                    <option value="">Ambos</option>
                    <option value="bien">Bien</option>
                    <option value="servicio">Servicio</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Porcentaje (%) *</label>
                <input type="number" step="0.01" wire:model="porcentaje" placeholder="Ej: 2" class="form-input w-full @error('porcentaje') border-rose-500 @enderror" />
                @error('porcentaje') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
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
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por departamento o retención..." class="form-input w-full max-w-xs" />
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Retención</th>
                    <th class="px-4 py-3 text-left">Departamento</th>
                    <th class="px-4 py-3 text-left">Tipo Adquisición</th>
                    <th class="px-4 py-3 text-right">Porcentaje</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->tarifas as $tarifa)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $tarifa->retencion->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $tarifa->departamento }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $tarifa->tipo_adquisicion ? ucfirst($tarifa->tipo_adquisicion) : 'Ambos' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $tarifa->porcentaje }}%</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $tarifa->id }})" class="text-violet-500 hover:text-violet-600 mr-3" title="Editar">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button type="button" wire:click="confirmDelete({{ $tarifa->id }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay tarifas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->tarifas->links() }}</div>

    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Eliminar Tarifa</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6">¿Estás seguro de eliminar esta tarifa de estampilla?</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="closeDeleteModal" class="btn border">Cancelar</button>
                    <button type="button" wire:click="delete" class="btn bg-rose-600 hover:bg-rose-700 text-white border border-rose-600">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
