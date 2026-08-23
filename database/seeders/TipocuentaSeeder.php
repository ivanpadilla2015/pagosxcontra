<?php

namespace Database\Seeders;

use App\Models\Tipocuenta;
use Illuminate\Database\Seeder;

class TipocuentaSeeder extends Seeder
{
    /**
     * Registros iniciales de tipos de cuenta.
     *
     * @return void
     */
    public function run()
    {
        $tipos = [
            ['name' => 'cuenta de Ahorro'],
            ['name' => 'Cuenta Corriente'],
        ];

        foreach ($tipos as $tipo) {
            Tipocuenta::firstOrCreate(['name' => $tipo['name']], $tipo);
        }
    }
}
