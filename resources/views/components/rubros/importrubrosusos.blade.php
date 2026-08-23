<?php

use App\Imports\RubrosImport;
use App\Imports\UsosImport;
use App\Models\Rubro;
use App\Models\Uso;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Componente Volt (Livewire 4) para importar los catálogos de Rubros y Usos
 * desde archivos Excel usando maatwebsite/excel.
 *
 * - Rubros: columnas [codigo_rubro, nombre_rubro].
 * - Usos:   columnas [codigo_uso, nombre_uso, rubro_id].
 *
 * Cada importación reemplaza por completo el catálogo correspondiente
 * (se vacía la tabla antes de importar) para mantener la integridad de los
 * IDs referenciados por los usos.
 */
new class extends Component
{
    // Habilita la subida de archivos temporales de Livewire.
    use WithFileUploads;

    /** Archivo Excel de rubros seleccionado. */
    public $rubrosFile;

    /** Archivo Excel de usos seleccionado. */
    public $usosFile;

    // ---------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS
    // ---------------------------------------------------------------------

    /** Cantidad actual de rubros en la base de datos. */
    #[Computed]
    public function totalRubros(): int
    {
        return Rubro::count();
    }

    /** Cantidad actual de usos en la base de datos. */
    #[Computed]
    public function totalUsos(): int
    {
        return Uso::count();
    }

    // ---------------------------------------------------------------------
    // IMPORTACIONES
    // ---------------------------------------------------------------------

    /**
     * Importa el catálogo de Rubros desde el archivo seleccionado.
     * Vacía la tabla rubros (y usos, por dependencia) antes de importar.
     */
    public function importRubros(): void
    {
        $this->validate([
            'rubrosFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['rubrosFile' => 'archivo de rubros']);

        try {
            // Se vacían usos y rubros para reiniciar los IDs autoincrementales
            // y evitar referencias huérfanas (los usos dependen de rubros).
            Schema::disableForeignKeyConstraints();
            Uso::truncate();
            Rubro::truncate();
            Schema::enableForeignKeyConstraints();

            Excel::import(new RubrosImport, $this->rubrosFile);

            $this->reset('rubrosFile');
            session()->flash('message', 'Rubros importados correctamente. Recuerda importar los usos nuevamente.');
        } catch (\Throwable $e) {
            Schema::enableForeignKeyConstraints();
            session()->flash('error', 'Error al importar rubros: '.$e->getMessage());
        }
    }

    /**
     * Importa el catálogo de Usos desde el archivo seleccionado.
     * Requiere que existan rubros previamente importados.
     */
    public function importUsos(): void
    {
        $this->validate([
            'usosFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['usosFile' => 'archivo de usos']);

        if (Rubro::count() === 0) {
            session()->flash('error', 'Debes importar primero los rubros antes de los usos.');

            return;
        }

        try {
            Uso::truncate();

            Excel::import(new UsosImport, $this->usosFile);

            $this->reset('usosFile');
            session()->flash('message', 'Usos importados correctamente.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Error al importar usos: '.$e->getMessage());
        }
    }
};

?>

{{-- =====================================================================
     VISTA (HTML + Blade)
     ===================================================================== --}}
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    {{-- Encabezado --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Importar Rubros / Usos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Carga los catálogos desde archivos Excel (.xlsx, .xls o .csv).</p>
    </div>

    {{-- Mensajes de éxito / error --}}
    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-700/60 dark:bg-rose-900/20 dark:text-rose-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- ============================= RUBROS ============================= --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-violet-100 dark:bg-violet-900/30 mr-3">
                        <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Rubros</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->totalRubros }} registrados</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Columnas esperadas (sin encabezado): <span class="font-medium">código de rubro</span>, <span class="font-medium">nombre de rubro</span>.</p>

            <div wire:loading.remove wire:target="importRubros">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo Excel de rubros</label>
                <input type="file" wire:model="rubrosFile" accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 dark:file:bg-violet-900/30 dark:file:text-violet-300" />
                @error('rubrosFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                @if ($rubrosFile)
                    <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Archivo seleccionado: {{ $rubrosFile->getClientOriginalName() }}</p>
                @endif
            </div>

            {{-- Indicador de carga durante la importación --}}
            <div wire:loading wire:target="importRubros" class="flex items-center text-sm text-violet-600 dark:text-violet-400 py-4">
                <svg class="animate-spin w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Importando rubros...
            </div>

            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-400">
                Al importar rubros se vacían los rubros y usos existentes. Deberás importar los usos de nuevo.
            </div>

            <button type="button" wire:click="importRubros" wire:loading.attr="disabled" wire:target="importRubros,rubrosFile"
                class="btn w-full mt-4 bg-violet-600 hover:bg-violet-700 text-white border border-violet-600 disabled:opacity-50 disabled:cursor-not-allowed">
                Importar Rubros
            </button>
        </div>

        {{-- ============================= USOS ============================= --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-sky-100 dark:bg-sky-900/30 mr-3">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Usos</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->totalUsos }} registrados</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Columnas esperadas (sin encabezado): <span class="font-medium">código de uso</span>, <span class="font-medium">nombre de uso</span>, <span class="font-medium">id de rubro</span>.</p>

            <div wire:loading.remove wire:target="importUsos">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo Excel de usos</label>
                <input type="file" wire:model="usosFile" accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-900/30 dark:file:text-sky-300" />
                @error('usosFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                @if ($usosFile)
                    <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Archivo seleccionado: {{ $usosFile->getClientOriginalName() }}</p>
                @endif
            </div>

            {{-- Indicador de carga durante la importación --}}
            <div wire:loading wire:target="importUsos" class="flex items-center text-sm text-sky-600 dark:text-sky-400 py-4">
                <svg class="animate-spin w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Importando usos...
            </div>

            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-400">
                Importa primero los rubros. Al importar usos se reemplazan los usos existentes.
            </div>

            <button type="button" wire:click="importUsos" wire:loading.attr="disabled" wire:target="importUsos,usosFile"
                class="btn w-full mt-4 bg-sky-600 hover:bg-sky-700 text-white border border-sky-600 disabled:opacity-50 disabled:cursor-not-allowed">
                Importar Usos
            </button>
        </div>
    </div>
</div>
