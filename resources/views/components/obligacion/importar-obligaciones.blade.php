<?php

use App\Imports\ObligacionesImport;
use App\Models\Contrato;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    use WithFileUploads;

    public string $numcontrato = '';
    public ?int $contratoId = null;
    public string $contratoInfo = '';
    public bool $contratoEncontrado = false;

    public $archivoImportacion;

    public ?array $resultadoImportacion = null;

    protected function rules(): array
    {
        return [
            'numcontrato' => ['required', 'string'],
            'archivoImportacion' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    #[Computed]
    public function totalObligaciones(): int
    {
        if (!$this->contratoId) {
            return 0;
        }

        return \App\Models\Obligacion::where('contrato_id', $this->contratoId)->count();
    }

    public function buscarContrato(): void
    {
        $this->resetValidation();
        $this->contratoEncontrado = false;
        $this->contratoId = null;
        $this->contratoInfo = '';
        $this->resultadoImportacion = null;

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
        $this->contratoInfo = "Contrato: {$contrato->numcontrato} | Proveedor: {$proveedor} | Objeto: {$objeto}";
    }

    public function updatedNumcontrato(): void
    {
        if (empty($this->numcontrato)) {
            $this->contratoEncontrado = false;
            $this->contratoId = null;
            $this->contratoInfo = '';
            $this->resultadoImportacion = null;
        }
    }

    public function importarObligaciones(): void
    {
        $this->validate([
            'archivoImportacion' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['archivoImportacion' => 'archivo Excel']);

        try {
            $import = new ObligacionesImport($this->contratoId);
            Excel::import($import, $this->archivoImportacion);

            $this->resultadoImportacion = [
                'creados' => $import->creados,
                'omitidos' => $import->omitidos,
                'errores' => $import->errores,
            ];

            $this->reset('archivoImportacion');
            session()->flash('message', "Importación completada: {$import->creados} creados, {$import->omitidos} omitidos.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Error al importar: ' . $e->getMessage());
        }
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Importar Obligaciones Masivas</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Carga masiva de obligaciones desde archivos Excel (.xlsx, .xls o .csv) para un contrato específico.</p>
    </div>

    @if (session('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed top-4 right-4 z-50 max-w-sm rounded-lg bg-emerald-50 border border-emerald-300 px-4 py-3 shadow-lg">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span class="text-sm font-medium text-emerald-700">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed top-4 right-4 z-50 max-w-sm rounded-lg bg-rose-50 border border-rose-300 px-4 py-3 shadow-lg">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <span class="text-sm font-medium text-rose-700">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Buscar contrato --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
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

        @if ($contratoEncontrado)
            <div class="mt-3 px-4 py-3 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 dark:bg-violet-900/30 dark:border-violet-700 dark:text-violet-400">
                {{ $contratoInfo }}
                <span class="ml-2 text-xs font-semibold">({{ $this->totalObligaciones }} obligaciones existentes)</span>
            </div>
        @endif
    </div>

    @if ($contratoEncontrado)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Formulario de importación --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-violet-100 dark:bg-violet-900/30 mr-3">
                            <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Importar Obligaciones</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->totalObligaciones }} obligaciones registradas en este contrato</p>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Columnas esperadas (con encabezado): <span class="font-medium">Numeral</span>, <span class="font-medium">Obligación (Detalle)</span>, <span class="font-medium">Entregable</span>.</p>

                <div wire:loading.remove wire:target="importarObligaciones">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo Excel de obligaciones</label>
                    <input type="file" wire:model="archivoImportacion" accept=".xlsx,.xls,.csv"
                        class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 dark:file:bg-violet-900/30 dark:file:text-violet-300" />
                    @error('archivoImportacion') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                    @if ($archivoImportacion)
                        <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">Archivo seleccionado: {{ $archivoImportacion->getClientOriginalName() }}</p>
                    @endif
                </div>

                <div wire:loading wire:target="importarObligaciones" class="flex items-center text-sm text-violet-600 dark:text-violet-400 py-4">
                    <svg class="animate-spin w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Importando obligaciones...
                </div>

                <button type="button" wire:click="importarObligaciones" wire:loading.attr="disabled" wire:target="importarObligaciones,archivoImportacion"
                    class="btn w-full mt-4 bg-violet-600 hover:bg-violet-700 text-white border border-violet-600 disabled:opacity-50 disabled:cursor-not-allowed">
                    Importar Obligaciones
                </button>
            </div>

            {{-- Resultado de importación --}}
            @if ($resultadoImportacion)
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-sky-100 dark:bg-sky-900/30 mr-3">
                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Resultado de la Importación</h2>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/60">
                            <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $resultadoImportacion['creados'] }}</span>
                            <span class="text-sm text-emerald-700 dark:text-emerald-400">Obligaciones creadas</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/60">
                            <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $resultadoImportacion['omitidos'] }}</span>
                            <span class="text-sm text-amber-700 dark:text-amber-400">Omitidas (ya existían)</span>
                        </div>
                        @if (count($resultadoImportacion['errores']) > 0)
                            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700/60">
                                <p class="text-sm font-medium text-rose-700 dark:text-rose-400 mb-2">Errores encontrados:</p>
                                <ul class="list-disc list-inside text-xs text-rose-600 dark:text-rose-400 max-h-48 overflow-y-auto">
                                    @foreach ($resultadoImportacion['errores'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Plantilla de referencia --}}
        <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Plantilla Excel</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Descarga la plantilla con la estructura correcta para importar obligaciones.</p>
                </div>
                <a href="{{ route('importar.obligaciones.plantilla') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descargar Plantilla
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left">Numeral</th>
                            <th class="px-4 py-3 text-left">Obligación (Detalle)</th>
                            <th class="px-4 py-3 text-left">Entregable</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr class="bg-gray-50 dark:bg-gray-700/30">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Cláusula 1.1</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">El contratista se compromete a ejecutar el objeto del contrato...</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Plan de trabajo aprobado</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Cláusula 2.3</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">El contratista entregará informes de avance mensuales.</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Informe mensual de avance</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-400">
                <strong>Nota:</strong> Las obligaciones duplicadas (mismo numeral + obligación) se omiten automáticamente. Se validan las 3 columnas como requeridas.
            </div>
        </div>
    @endif
</div>
