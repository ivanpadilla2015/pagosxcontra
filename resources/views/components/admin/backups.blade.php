<?php

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public bool $backupEnabled = true;
    public string $backupTime = '02:00';
    public string $backupEmail = '';

    public bool $executingBackup = false;
    public bool $executingClean = false;
    public string $backupOutput = '';

    public bool $deleteModalOpen = false;
    public string $fileToDelete = '';

    public array $backupsList = [];

    public function mount(): void
    {
        $this->backupEnabled = Setting::get('backup_enabled', '1') === '1';
        $this->backupTime = Setting::get('backup_time', '02:00');
        $this->backupEmail = Setting::get('backup_email', '');
        $this->loadBackups();
    }

    public function loadBackups(): void
    {
        $backupPath = storage_path('app/' . config('backup.backup.name'));

        if (!File::isDirectory($backupPath)) {
            $this->backupsList = [];
            return;
        }

        $this->backupsList = collect(File::files($backupPath))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->sortByDesc('filename')
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'date' => $file->getMTime(),
                'path' => $file->getPathname(),
            ])
            ->values()
            ->toArray();
    }

    public function runBackup(): void
    {
        $this->executingBackup = true;
        $this->backupOutput = '';

        try {
            $phpBinary = PHP_BINARY;
            $artisanPath = base_path('artisan');
            $cmd = '"' . $phpBinary . '" "' . $artisanPath . '" backup:run --no-interaction 2>&1';

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            $this->backupOutput = implode("\n", $output);
            $this->loadBackups();

            if ($returnCode === 0) {
                session()->flash('message', 'Backup ejecutado correctamente.');
            } else {
                session()->flash('error', 'El backup fallo. Verifique el resultado abajo.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error al ejecutar backup: ' . $e->getMessage());
        }

        $this->executingBackup = false;
    }

    public function cleanBackups(): void
    {
        $this->executingClean = true;

        try {
            $phpBinary = PHP_BINARY;
            $artisanPath = base_path('artisan');
            $cmd = '"' . $phpBinary . '" "' . $artisanPath . '" backup:clean --no-interaction 2>&1';

            exec($cmd);

            $this->loadBackups();
            session()->flash('message', 'Limpieza de backups ejecutada correctamente.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al limpiar backups: ' . $e->getMessage());
        }

        $this->executingClean = false;
    }

    public function confirmDelete(string $fileName): void
    {
        $this->fileToDelete = $fileName;
        $this->deleteModalOpen = true;
    }

    public function deleteBackup(): void
    {
        $backupName = config('backup.backup.name');
        $filePath = storage_path('app/' . $backupName . '/' . $this->fileToDelete);

        if (File::exists($filePath)) {
            File::delete($filePath);
            $this->loadBackups();
            session()->flash('message', 'Backup eliminado correctamente.');
        } else {
            session()->flash('error', 'Archivo no encontrado.');
        }

        $this->deleteModalOpen = false;
        $this->fileToDelete = '';
    }

    public function saveSettings(): void
    {
        Setting::set('backup_enabled', $this->backupEnabled ? '1' : '0');
        Setting::set('backup_time', $this->backupTime);
        Setting::set('backup_email', $this->backupEmail);

        if ($this->backupEmail) {
            config(['backup.notifications.mail.to' => $this->backupEmail]);
        }

        session()->flash('message', 'Configuracion de backup guardada correctamente.');
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
?>

<div>
    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Configuracion de Backups</h1>
    </div>

    {{-- Tabs --}}
    <div x-data="{ activeTab: 'manual' }">
        <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
            <button
                @click="activeTab = 'manual'"
                :class="activeTab === 'manual' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                Backup Manual
            </button>
            <button
                @click="activeTab = 'automatico'"
                :class="activeTab === 'automatico' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                Backup Automatico
            </button>
        </div>

        {{-- Tab: Backup Manual --}}
        <div x-show="activeTab === 'manual'" x-cloak>
            {{-- Action Buttons --}}
            <div class="flex flex-wrap gap-3 mb-6">
                <a
                    href="{{ route('admin.backups.descargar-fuente') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar Codigo Fuente
                </a>

                <a
                    href="{{ route('admin.backups.descargar-base-datos') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-sky-500 hover:bg-sky-600 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    Descargar Base de Datos
                </a>

                <button
                    wire:click="runBackup"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-violet-500 hover:bg-violet-600 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg wire:loading.remove wire:target="runBackup" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <svg wire:loading wire:target="runBackup" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="runBackup">Crear Backup Completo (BD + Fuente)</span>
                    <span wire:loading wire:target="runBackup">Creando backup...</span>
                </button>

                <button
                    wire:click="cleanBackups"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-500 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg wire:loading.remove wire:target="cleanBackups" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <svg wire:loading wire:target="cleanBackups" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="cleanBackups">Limpiar Backups Antiguos</span>
                    <span wire:loading wire:target="cleanBackups">Limpiando...</span>
                </button>
            </div>

            {{-- Backup Output --}}
            @if ($backupOutput)
                <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Resultado del Backup:</h3>
                    <pre class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $backupOutput }}</pre>
                </div>
            @endif

            {{-- Backups List --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/50">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Backups Existentes</h2>
                </div>

                @if (empty($backupsList))
                    <div class="px-5 py-10 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-sm">No hay backups disponibles</p>
                        <p class="text-xs mt-1">Haz clic en "Crear Backup Completo" para crear el primer backup</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                    <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tamaño</th>
                                    <th class="text-right px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                @foreach ($backupsList as $backup)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-5 py-3">
                                            <span class="text-gray-800 dark:text-gray-200 font-medium">{{ $backup['name'] }}</span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::createFromTimestamp($backup['date'])->setTimezone('America/Bogota')->format('d/m/Y H:i:s') }}</span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-gray-600 dark:text-gray-400">{{ $this->formatSize($backup['size']) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a
                                                    href="{{ route('admin.backups.download', $backup['name']) }}"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-500/10 rounded-lg hover:bg-violet-100 dark:hover:bg-violet-500/20 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                    </svg>
                                                    Descargar
                                                </a>
                                                <button
                                                    wire:click="confirmDelete('{{ $backup['name'] }}')"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 rounded-lg hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tab: Backup Automatico --}}
        <div x-show="activeTab === 'automatico'" x-cloak>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 max-w-xl">
                <form wire:submit="saveSettings">
                    <div class="mb-5">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="backupEnabled" class="w-5 h-5 rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Habilitar backup automatico</span>
                        </label>
                    </div>

                    <div class="mb-5">
                        <label for="backupTime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Hora del backup diario</label>
                        <input type="time" id="backupTime" wire:model="backupTime" {{ $backupEnabled ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">El backup se ejecutara automaticamente a esta hora todos los dias.</p>
                    </div>

                    <div class="mb-6">
                        <label for="backupEmail" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email de notificacion</label>
                        <input type="email" id="backupEmail" wire:model="backupEmail" placeholder="admin@ejemplo.com" {{ $backupEnabled ? '' : 'disabled' }}
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Recibira un email cuando el backup termine. Opcional.</p>
                    </div>

                    <div class="mb-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Requisito importante</p>
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">Para que los backups automaticos funcionen, configure el programador de tareas:</p>
                                <div class="mt-3 space-y-3">
                                    <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-amber-100 dark:border-amber-500/10">
                                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 mb-1">Windows (desarrollo local)</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-400">Ejecute como Administrador el archivo <code class="px-1 py-0.5 bg-amber-100 dark:bg-amber-500/20 rounded">install-scheduler.bat</code></p>
                                    </div>
                                    <div class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-amber-100 dark:border-amber-500/10">
                                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 mb-1">Linux / Hosting</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-400 mb-2">Agregue este Cron Job desde el panel de su hosting:</p>
                                        <code class="block p-2 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-800 dark:text-gray-200 break-all">* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-500 hover:bg-violet-600 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Guardar Configuracion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data>
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 transition-opacity" wire:click="$set('deleteModalOpen', false)"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-500/10 mb-4">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Eliminar Backup</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Estas seguro de que deseas eliminar <strong>{{ $fileToDelete }}</strong>?</p>
                        <div class="flex gap-3 justify-center">
                            <button wire:click="$set('deleteModalOpen', false)" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancelar</button>
                            <button wire:click="deleteBackup" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
