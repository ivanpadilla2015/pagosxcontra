<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('producto_retencion')->truncate();
        DB::table('productos')->truncate();
        Schema::enableForeignKeyConstraints();

        $productos = [
            // HORTALIZAS (uso_id: 2, rubro_id: 1)
            ['name' => 'AHUYAMA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'APIO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'ARVEJA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'CEBOLLA CABEZONA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'CEBOLLA LARGA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'CEBOLLA ROJA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'CHAMPIÑONES ORELLANA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'ESPINACA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'FRIJOL', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'HABICHUELA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'JORQUERA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'LAUREL FRESCO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MAZORCA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'OREGANO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PEPINO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PEREJIL', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PIMENTON', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'REMOLACHA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'REPOLLO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'TOMATE COCINA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'ZANAHORIA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'AJI CRIOLLO', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'AJI CRIOLLO DULCE', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'BERENJENA', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'BROCOLI', 'tipo' => 'bien', 'uso_id' => 2, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],

            // FRUTAS Y NUECES (uso_id: 3, rubro_id: 1)
            ['name' => 'AGUACATE', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'BANANO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'COCO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'COROZO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'CURUBA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'DURAZNO CRIOLLO GRANDE', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'FRESA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'GRANADILLA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'GUANABANA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'GUAYABA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'GUAYMANGO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'HORTONUDA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'LIMON', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'LULO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MANDARINA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MANGO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MANZANA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MARACUYA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MELON', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'MORA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'NARANJA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PAPAYA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PATILLA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PERA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PIÑA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PLATANO AMARILLO', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PLATANO VERDE', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'TOMATE DE ARBOL', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'TROPICALEÑA', 'tipo' => 'bien', 'uso_id' => 3, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],

            // RAICES Y TUBERCULOS (uso_id: 5, rubro_id: 1)
            ['name' => 'ARRACAHA', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'ÑAME', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PAPA CRIOLLA', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PAPA PASTUSA', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'PAPA SABANERA', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],
            ['name' => 'YUCA', 'tipo' => 'bien', 'uso_id' => 5, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],

            // CEREALES (uso_id: 1, rubro_id: 1)
            ['name' => 'RABANO', 'tipo' => 'bien', 'uso_id' => 1, 'rubro_id' => 1, 'regional_id' => 1, 'es_agricola' => true],

           /* // SERVICIOS (rubro_id: 151)
            ['name' => 'Mantenimiento Impresora de Biver', 'tipo' => 'servicio', 'uso_id' => 944, 'rubro_id' => 151, 'regional_id' => 1, 'es_agricola' => false],
            ['name' => 'Mantenimiento Planta Electrica', 'tipo' => 'servicio', 'uso_id' => 938, 'rubro_id' => 151, 'regional_id' => 1, 'es_agricola' => false],

            // BIENES (rubro_id: 151)
            ['name' => 'Disco Duro Sata 1 Tera', 'tipo' => 'bien', 'uso_id' => 944, 'rubro_id' => 151, 'regional_id' => 1, 'es_agricola' => false],
            ['name' => 'Pantalla PC HP 400 G2', 'tipo' => 'bien', 'uso_id' => 943, 'rubro_id' => 151, 'regional_id' => 1, 'es_agricola' => false],*/
        ];

        foreach ($productos as $producto) {
            DB::table('productos')->insert($producto);
        }

        // Retenciones parafiscales para productos de papa (retencion_id: 4 = Fedepapa)
        $productosPapa = ['PAPA PASTUSA', 'PAPA CRIOLLA', 'PAPA SABANERA'];
        foreach ($productosPapa as $nombre) {
            $producto = DB::table('productos')->where('name', $nombre)->first();
            if ($producto) {
                DB::table('producto_retencion')->insert([
                    'producto_id' => $producto->id,
                    'retencion_id' => 4, // Fedepapa
                ]);
            }
        }

        $this->command?->info('Productos y retenciones parafiscales creados correctamente (' . count($productos) . ' productos).');
    }
}
