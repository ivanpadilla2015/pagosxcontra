<?php

namespace Database\Seeders;

use App\Imports\RubrosImport;
use App\Imports\UsosImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class RubrosUsosSeeder extends Seeder
{
    public function run(): void
    {
        $rubrosPath = public_path('Formatos/rubros.xlsx');
        $usosPath = public_path('Formatos/usos.xlsx');

        if (!File::exists($rubrosPath)) {
            $this->command?->error('No se encontró el archivo: public/Formatos/rubros.xlsx');

            return;
        }

        Schema::disableForeignKeyConstraints();
        \App\Models\Uso::truncate();
        \App\Models\Rubro::truncate();
        Schema::enableForeignKeyConstraints();

        Excel::import(new RubrosImport, $rubrosPath);
        $this->command?->info('Rubros importados correctamente.');

        if (File::exists($usosPath)) {
            Excel::import(new UsosImport, $usosPath);
            $this->command?->info('Usos importados correctamente.');
        } else {
            $this->command?->warn('No se encontró el archivo de usos: public/Formatos/usos.xlsx');
        }
    }
}
