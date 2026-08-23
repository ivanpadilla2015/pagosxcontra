<?php

namespace App\Console;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        try {
            $backupEnabled = Setting::get('backup_enabled', '1');
            $backupTime = Setting::get('backup_time', '02:00');
        } catch (\Exception $e) {
            $backupEnabled = '1';
            $backupTime = '02:00';
        }

        if ($backupEnabled) {
            $schedule->command('backup:run')->dailyAt($backupTime);
            $schedule->command('backup:clean')->dailyAt($backupTime);
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
