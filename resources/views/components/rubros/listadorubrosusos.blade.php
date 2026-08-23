<?php

use App\Models\Uso;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Volt (Livewire 4) para listar los Usos junto con su Rubro,
 * permitiendo buscar por nombre o código del uso.
 */
new class extends Component
{
    use WithPagination;

    /**
     * Texto del buscador (busca en código y nombre del uso).
     * #[Url] mantiene la búsqueda en la URL (?search=...).
     */
    #[Url]
    public string $search = '';

    /**
     * Reinicia la paginación cuando cambia la búsqueda.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Lista paginada de usos con su rubro asociado, filtrada por búsqueda.
     */
    #[Computed]
    public function usos()
    {
        return Uso::query()
            ->with('rubro')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('codigo_uso', 'like', '%'.$this->search.'%')
                        ->orWhere('nombre_uso', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('codigo_uso')
            ->paginate(6);
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Listado de Rubros / Usos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Consulta los usos y el rubro al que pertenecen.</p>
    </div>

    {{-- Campo de búsqueda --}}
    <div class="mb-4">
        <div class="relative max-w-md">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por código o nombre de uso..." class="form-input w-full pl-9" />
            <div class="absolute inset-0 right-auto flex items-center pointer-events-none pl-3">
                <svg class="w-4 h-4 fill-current text-gray-400 dark:text-gray-500" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 14c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7zM7 2C4.243 2 2 4.243 2 7s2.243 5 5 5 5-2.243 5-5-2.243-5-5-5z" />
                    <path d="M15.707 14.293L13.314 11.9a8.019 8.019 0 01-1.414 1.414l2.393 2.393a.997.997 0 001.414 0 .999.999 0 000-1.414z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Tabla con la lista paginada de usos --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <table class="table-auto w-full">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                <tr>
                    <th class="px-4 py-3 text-left">Código Uso</th>
                    <th class="px-4 py-3 text-left">Nombre Uso</th>
                    <th class="px-4 py-3 text-left">Rubro</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse ($this->usos as $uso)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-800 dark:text-gray-100">{{ $uso->codigo_uso }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $uso->nombre_uso }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                            @if ($uso->rubro)
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $uso->rubro->codigo_rubro }}</span>
                                <span class="block">{{ $uso->rubro->nombre_rubro }}</span>
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Sin rubro</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No se encontraron usos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Controles de paginación --}}
    <div class="mt-4">
        {{ $this->usos->links() }}
    </div>
</div>
