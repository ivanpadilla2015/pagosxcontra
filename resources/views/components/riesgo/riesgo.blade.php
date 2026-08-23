<?php

use App\Models\Riesgo;
use App\Models\Contrato;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $numcontrato = '';
    public ?int $contratoId = null;
    public string $contratoInfo = '';
    public bool $contratoEncontrado = false;

    public bool $modalOpen = false;
    public bool $deleteModalOpen = false;
    public ?int $editingId = null;
    public ?int $riesgoToDeleteId = null;
    public string $riesgoToDeleteName = '';

    public string $tipo = '';
    public string $descripcion = '';
    public string $tratamiento = '';
    public string $responsable = '';
    public string $periodicidad = '';

    protected function rules(): array
    {
        return [
            'numcontrato' => ['required', 'string'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['required', 'string'],
            'tratamiento' => ['required', 'string'],
            'responsable' => ['required', 'string', 'max:255'],
            'periodicidad' => ['nullable', 'string', 'max:255'],
        ];
    }

    #[Computed]
    public function riesgos()
    {
        if (!$this->contratoId) {
            return collect();
        }

        return Riesgo::query()
            ->where('contrato_id', $this->contratoId)
            ->latest()
            ->paginate(10);
    }

    public function buscarContrato(): void
    {
        $this->resetValidation();
        $this->contratoEncontrado = false;
        $this->contratoId = null;
        $this->contratoInfo = '';

        $this->validateOnly('numcontrato');

        $contrato = Contrato::where('numcontrato', $this->numcontrato)->first();

        if (!$contrato) {
            session()->flash('error', 'No se encontró un contrato con ese número.');
            return;
        }

        $this->contratoId = $contrato->id;
        $this->contratoEncontrado = true;
        $proveedor = $contrato->proveedor ? $contrato->proveedor->nombre : 'N/A';
        $objeto = $contrato->objetocontrato ?? 'Sin objeto';
        $valor = '$' . number_format($contrato->valorTotal, 2, ',', '.');
        $this->contratoInfo = "Contrato: {$contrato->numcontrato} | Proveedor: {$proveedor} | Objeto: {$objeto} | Valor: {$valor}";
    }

    public function updatedNumcontrato(): void
    {
        if (empty($this->numcontrato)) {
            $this->contratoEncontrado = false;
            $this->contratoId = null;
            $this->contratoInfo = '';
        }
    }

    public function openModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset(['tipo', 'descripcion', 'tratamiento', 'responsable', 'periodicidad', 'editingId']);
        $this->editingId = $id;

        if ($id) {
            $riesgo = Riesgo::findOrFail($id);
            $this->tipo = $riesgo->tipo ?? '';
            $this->descripcion = $riesgo->descripcion;
            $this->tratamiento = $riesgo->tratamiento;
            $this->responsable = $riesgo->responsable;
            $this->periodicidad = $riesgo->periodicidad ?? '';
        }

        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset(['tipo', 'descripcion', 'tratamiento', 'responsable', 'periodicidad', 'editingId']);
        $this->resetValidation();
    }

    public function confirmDelete(Riesgo $riesgo): void
    {
        $this->riesgoToDeleteId = $riesgo->id;
        $this->riesgoToDeleteName = $riesgo->tipo ?? $riesgo->descripcion;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->riesgoToDeleteId = null;
        $this->riesgoToDeleteName = '';
    }

    public function delete(): void
    {
        Riesgo::findOrFail($this->riesgoToDeleteId)->delete();
        session()->flash('message', 'Riesgo eliminado correctamente.');
        $this->closeDeleteModal();
    }

    public function save(): void
    {
        $this->validate([
            'tipo' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['required', 'string'],
            'tratamiento' => ['required', 'string'],
            'responsable' => ['required', 'string', 'max:255'],
            'periodicidad' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'tipo' => $this->tipo ?: null,
            'descripcion' => $this->descripcion,
            'tratamiento' => $this->tratamiento,
            'responsable' => $this->responsable,
            'periodicidad' => $this->periodicidad ?: null,
            'contrato_id' => $this->contratoId,
        ];

        if ($this->editingId) {
            Riesgo::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Riesgo actualizado correctamente.');
        } else {
            Riesgo::create($data);
            session()->flash('message', 'Riesgo creado correctamente.');
        }

        $this->closeModal();
    }
};
?>

<div>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Riesgos por Contrato</h1>
        </div>

        <!-- Flash messages -->
        @if (session()->has('message'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-400">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <!-- Buscar contrato -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Contrato <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model.live="numcontrato"
                    wire:keydown.enter="buscarContrato"
                    class="w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                    placeholder="Ej: 010-009-2026"
                />
                <button wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                    Buscar
                </button>
            </div>
            @error('numcontrato')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Info del contrato encontrado -->
        @if ($contratoEncontrado)
            <div class="mb-6 px-4 py-3 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 dark:bg-violet-900/30 dark:border-violet-700 dark:text-violet-400">
                {{ $contratoInfo }}
            </div>

            <!-- Botón nuevo riesgo -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                    + Nuevo Riesgo
                </button>
            </div>

            <!-- Tabla de riesgos -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">N°</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tratamiento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Responsable</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Periodicidad</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse ($this->riesgos as $riesgo)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo->tipo ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo->descripcion }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo->tratamiento }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo->responsable }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo->periodicidad ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button wire:click="openModal({{ $riesgo->id }})" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 mr-2" title="Editar">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $riesgo->id }})" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay riesgos registrados para este contrato.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            @if ($this->riesgos->hasPages())
                <div class="mt-4">
                    {{ $this->riesgos->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- Modal CREAR/EDITAR -->
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="riesgo-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
                    {{ $editingId ? 'Editar Riesgo' : 'Nuevo Riesgo' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                        <input
                            type="text"
                            wire:model="tipo"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ej: Estratégico, Operativo, Financiero"
                        />
                        @error('tipo')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción <span class="text-red-500">*</span></label>
                        <textarea
                            wire:model="descripcion"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Descripción del riesgo"
                        ></textarea>
                        @error('descripcion')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tratamiento <span class="text-red-500">*</span></label>
                        <textarea
                            wire:model="tratamiento"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Tratamiento del riesgo"
                        ></textarea>
                        @error('tratamiento')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Responsable <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            wire:model="responsable"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Nombre del responsable"
                        />
                        @error('responsable')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Periodicidad</label>
                        <input
                            type="text"
                            wire:model="periodicidad"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ej: Mensual, Trimestral, Anual"
                        />
                        @error('periodicidad')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="save" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition">
                        {{ $editingId ? 'Actualizar' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal ELIMINAR -->
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal" wire:key="delete-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 text-center">Eliminar Riesgo</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mt-2 mb-6">
                    ¿Está seguro que desea eliminar el riesgo <strong class="text-gray-800 dark:text-gray-200">{{ $riesgoToDeleteName }}</strong>? Esta acción no se puede deshacer.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeDeleteModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="delete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
