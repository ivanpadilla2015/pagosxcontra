<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('backup_enabled', '1');
        Setting::set('backup_time', '02:00');
        Setting::set('backup_email', '');
    }
}
