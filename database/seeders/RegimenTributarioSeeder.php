<?php

namespace Database\Seeders;

use App\Models\RegimenTributario;
use Illuminate\Database\Seeder;

class RegimenTributarioSeeder extends Seeder
{
    /**
     * Registros iniciales de regimenes tributarios.
     *
     * @return void
     */
    public function run()
    {
        $regimenes = [
            ['name' => 'No Responsable de IVA'],
            ['name' => 'Régimen Simple'],
            ['name' => 'Responsable de IVA'],
            ['name' => 'Gran Contribuyente'],
            ['name' => 'Autorretenedor'],
        ];

        foreach ($regimenes as $regimen) {
            RegimenTributario::firstOrCreate(['name' => $regimen['name']], $regimen);
        }
    }
}
