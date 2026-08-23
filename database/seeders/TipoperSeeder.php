<?php

namespace Database\Seeders;

use App\Models\Tipoper;
use Illuminate\Database\Seeder;

class TipoperSeeder extends Seeder
{
    /**
     * Registros iniciales de tipos de persona.
     *
     * @return void
     */
    public function run()
    {
        $tipos = [
            ['name' => 'Persona Juridica'],
            ['name' => 'Persona Natural'],
        ];

        foreach ($tipos as $tipo) {
            Tipoper::firstOrCreate(['name' => $tipo['name']], $tipo);
        }
    }
}
