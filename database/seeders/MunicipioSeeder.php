<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;

class MunicipioSeeder extends Seeder
{
    public function run()
    {
        $municipios = [
            ['nombre' => 'Malambo', 'departamento' => 'Atlántico', 'codigo_dane' => null, 'regional_id' => 1],
            ['nombre' => 'Barranquilla', 'departamento' => 'Atlantico', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Santa Marta', 'departamento' => 'Magdalena', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Buenavista', 'departamento' => 'La Guajira', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Monteria', 'departamento' => 'Cordoba', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Tierra alta', 'departamento' => 'Cordoba', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Caucacia', 'departamento' => 'Antioquia', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'La Mata', 'departamento' => 'Cesar', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'La Loma', 'departamento' => 'Cesar', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Riohacha', 'departamento' => 'La Guajira', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Albania', 'departamento' => 'Guajira', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Aracataca', 'departamento' => 'Magdalena', 'codigo_dane' => '', 'regional_id' => 1],
            ['nombre' => 'Valledupar', 'departamento' => 'Cesar', 'codigo_dane' => '', 'regional_id' => 1],
        ];

        foreach ($municipios as $municipio) {
            Municipio::updateOrCreate(
                ['nombre' => $municipio['nombre'], 'departamento' => $municipio['departamento']],
                $municipio
            );
        }
    }
}
