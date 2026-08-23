<?php

namespace Database\Seeders;

use App\Models\Tipocontrato;
use Illuminate\Database\Seeder;

class TipocontratoSeeder extends Seeder
{
    /**
     * Registros iniciales de tipos de contrato.
     *
     * @return void
     */
    public function run()
    {
        $tipos = [
            ['name' => 'Minima Cuantias'],
            ['name' => 'Selecion Abreviada'],
        ];

        foreach ($tipos as $tipo) {
            Tipocontrato::firstOrCreate(['name' => $tipo['name']], $tipo);
        }
    }
}
