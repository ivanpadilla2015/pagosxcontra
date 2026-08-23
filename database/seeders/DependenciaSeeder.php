<?php

namespace Database\Seeders;

use App\Models\Dependencia;
use App\Models\Municipio;
use Illuminate\Database\Seeder;

class DependenciaSeeder extends Seeder
{
    public function run()
    {
        $dependencias = [
            ['name' => 'Administrativa', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Servicios Administrativos', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Tecnologia', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Gestion Documental', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Comedor Biver', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Comedor Cacom3', 'municipio' => 'Malambo', 'departamento' => 'Atlántico'],
            ['name' => 'Comedor Grupo Rondon', 'municipio' => 'Buenavista', 'departamento' => 'La Guajira'],
            ['name' => 'Comedor Basab', 'municipio' => 'Buenavista', 'departamento' => 'La Guajira'],
            ['name' => 'Comedor Matamoros', 'municipio' => 'Albania', 'departamento' => 'Guajira'],
            ['name' => 'Comedor Bicar', 'municipio' => 'Riohacha', 'departamento' => 'La Guajira'],
            ['name' => 'Comedor Bicor', 'municipio' => 'Santa Marta', 'departamento' => 'Magdalena'],
            ['name' => 'Comedor Baeev3', 'municipio' => 'La Mata', 'departamento' => 'Cesar'],
            ['name' => 'Comedor Biter10', 'municipio' => 'La Loma', 'departamento' => 'Cesar'],
            ['name' => 'Comedor Biter2', 'municipio' => 'Aracataca', 'departamento' => 'Magdalena'],
            ['name' => 'Comedor Pm', 'municipio' => 'Barranquilla', 'departamento' => 'Atlantico'],
            ['name' => 'Comedor Baser2', 'municipio' => 'Barranquilla', 'departamento' => 'Atlantico'],
            ['name' => 'Comedor Rifles', 'municipio' => 'Caucacia', 'departamento' => 'Antioquia'],
            ['name' => 'Comedor Biter11', 'municipio' => 'Tierra alta', 'departamento' => 'Cordoba'],
            ['name' => 'Comedor Junin', 'municipio' => 'Monteria', 'departamento' => 'Cordoba'],
            ['name' => 'Comedor la Popa', 'municipio' => 'Valledupar', 'departamento' => 'Cesar'],
            ['name' => 'Comedor Bimur', 'municipio' => 'Valledupar', 'departamento' => 'Cesar'],
        ];

        foreach ($dependencias as $dep) {
            $municipio = Municipio::where('nombre', $dep['municipio'])->first();
            Dependencia::firstOrCreate(
                ['name' => $dep['name']],
                [
                    'municipio_id' => $municipio?->id,
                    'regional_id' => 1,
                ]
            );
        }
    }
}
