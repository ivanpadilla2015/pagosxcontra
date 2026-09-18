<?php

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\Rubro;
use App\Models\Uso;
use Illuminate\Database\Seeder;

class ProductoGenericoSeeder extends Seeder
{
    public function run(): void
    {
        $producto = Producto::where('name', 'Facturación Sencilla')->first();

        if ($producto) {
            return;
        }

        // Obtener el primer rubro y uso disponibles como defaults
        $rubro = Rubro::first();
        $uso = Uso::first();

        if (!$rubro || !$uso) {
            $this->command?->warn('No hay rubros o usos disponibles. Ejecute RubrosUsosSeeder primero.');
            return;
        }

        Producto::create([
            'name'          => 'Facturación Sencilla',
            'tipo'          => 'servicio',
            'uso_id'        => $uso->id,
            'rubro_id'      => $rubro->id,
            'es_agricola'   => false,
        ]);

        $this->command?->info('Producto genérico "Facturación Sencilla" creado.');
    }
}
