<?php

namespace Database\Seeders;

use App\Models\Director;
use App\Models\Presupuesto;
use App\Models\Regional;
use Illuminate\Database\Seeder;

class DirectorPresupuestoRegionalSeeder extends Seeder
{
    public function run(): void
    {
        $director = Director::firstOrCreate(
            ['name' => 'TC (R) Ricardo Jerez'],
            ['cargo' => 'Director Regional Norte']
        );

        $presupuesto = Presupuesto::firstOrCreate(
            ['name' => 'PD Laura Anibal santama'],
            ['cargo' => 'Presupuesto']
        );

        Regional::firstOrCreate(
            ['name' => 'Regional Norte'],
            [
                'director_id' => $director->id,
                'presupuesto_id' => $presupuesto->id,
                 'municipio_id' => '1',
                 'firma_nombre_coord_admin' => 'Emma Pernet de los Reyes',
                 'firma_cargo_admin' => 'Coordinadora Administrativa y de Talento Humano',
                 'firma_nombre_coord_contrato' => 'Leonar Reales Reales',
                 'firma_cargo_contrato' => 'Coordinador de Contratos',
            ]
        );
    }
}
