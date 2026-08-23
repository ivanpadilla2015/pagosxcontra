<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class RunBackup extends Command
{
    protected $signature = 'backup:manual';
    protected $description = 'Ejecuta backup manual desde la interfaz web';

    public function handle(): int
    {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        $result = Process::run([
            $phpBinary,
            $artisanPath,
            'backup:run',
            '--no-interaction',
        ], function (string $type, string $output) {
            $this->line($output);
        });

        if ($result->successful()) {
            $this->info('Backup completado exitosamente.');
            return Command::SUCCESS;
        }

        $this->error('Backup falló: ' . $result->errorOutput());
        return Command::FAILURE;
    }
}
