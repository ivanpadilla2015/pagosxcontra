<?php

use App\Models\Obligacion;
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
    public ?int $obligacionToDeleteId = null;
    public string $obligacionToDeleteName = '';

    public string $numeral = '';
    public string $obligacion_deta = '';
    public string $entregable = '';

    protected function rules(): array
    {
        return [
            'numcontrato' => ['required', 'string'],
            'numeral' => ['required', 'string'],
            'obligacion_deta' => ['required', 'string'],
            'entregable' => ['required', 'string', 'max:255'],
        ];
    }

    #[Computed]
    public function obligaciones()
    {
        if (!$this->contratoId) {
            return collect();
        }

        return Obligacion::query()
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
        $this->reset(['numeral', 'obligacion_deta', 'entregable', 'editingId']);
        $this->editingId = $id;

        if ($id) {
            $obligacion = Obligacion::findOrFail($id);
            $this->numeral = $obligacion->numeral;
            $this->obligacion_deta = $obligacion->obligacion_deta;
            $this->entregable = $obligacion->entregable;
        }

        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->reset(['numeral', 'obligacion_deta', 'entregable', 'editingId']);
        $this->resetValidation();
    }

    public function confirmDelete(Obligacion $obligacion): void
    {
        $this->obligacionToDeleteId = $obligacion->id;
        $this->obligacionToDeleteName = $obligacion->numeral;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->obligacionToDeleteId = null;
        $this->obligacionToDeleteName = '';
    }

    public function delete(): void
    {
        Obligacion::findOrFail($this->obligacionToDeleteId)->delete();
        session()->flash('message', 'Obligación eliminada correctamente.');
        $this->closeDeleteModal();
    }

    public function save(): void
    {
        $this->validate([
            'numeral' => ['required', 'string'],
            'obligacion_deta' => ['required', 'string'],
            'entregable' => ['required', 'string', 'max:255'],
        ]);

        $data = [
            'numeral' => $this->numeral,
            'obligacion_deta' => $this->obligacion_deta,
            'entregable' => $this->entregable,
            'contrato_id' => $this->contratoId,
        ];

        if ($this->editingId) {
            Obligacion::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Obligación actualizada correctamente.');
        } else {
            Obligacion::create($data);
            session()->flash('message', 'Obligación creada correctamente.');
        }

        $this->closeModal();
    }
};
?>

<div>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Obligaciones por Contrato</h1>
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

            <!-- Botón nueva obligación -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                    + Nueva Obligación
                </button>
            </div>

            <!-- Tabla de obligaciones -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">N°</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Numeral</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Obligación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Entregable</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse ($this->obligaciones as $obligacion)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion->numeral }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion->obligacion_deta }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion->entregable }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <button wire:click="openModal({{ $obligacion->id }})" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 mr-2" title="Editar">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $obligacion->id }})" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay obligaciones registradas para este contrato.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            @if ($this->obligaciones->hasPages())
                <div class="mt-4">
                    {{ $this->obligaciones->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- Modal CREAR/EDITAR -->
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="obligacion-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
                    {{ $editingId ? 'Editar Obligación' : 'Nueva Obligación' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Numeral <span class="text-red-500">*</span></label>
                        <textarea
                            wire:model="numeral"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese el numeral"
                        ></textarea>
                        @error('numeral')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Obligación (Detalle) <span class="text-red-500">*</span></label>
                        <textarea
                            wire:model="obligacion_deta"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese el detalle de la obligación"
                        ></textarea>
                        @error('obligacion_deta')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entregable <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            wire:model="entregable"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese el entregable"
                        />
                        @error('entregable')
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
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 text-center">Eliminar Obligación</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mt-2 mb-6">
                    ¿Está seguro que desea eliminar la obligación <strong class="text-gray-800 dark:text-gray-200">{{ $obligacionToDeleteName }}</strong>? Esta acción no se puede deshacer.
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
