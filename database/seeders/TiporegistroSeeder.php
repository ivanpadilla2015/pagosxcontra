<?php

namespace Database\Seeders;

use App\Models\Tiporegistro;
use Illuminate\Database\Seeder;

class TiporegistroSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['name' => 'Primer Registro'],
            ['name' => 'Adicion'],
            ['name' => 'Reduccion'],
            ['name' => 'Traslado'],
        ];

        foreach ($registros as $registro) {
            Tiporegistro::firstOrCreate(['name' => $registro['name']], $registro);
        }
    }
}
