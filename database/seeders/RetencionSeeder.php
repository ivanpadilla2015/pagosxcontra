<?php

namespace Database\Seeders;

use App\Models\Retencion;
use Illuminate\Database\Seeder;

class RetencionSeeder extends Seeder
{
    /**
     * Catálogo de retenciones disponibles.
     *
     * @return void
     */
    public function run()
    {
        $retenciones = [
            ['name' => 'Retefuente', 'tipo' => 'general', 'aplica_base' => true, 'aplica_iva' => false, 'divisor' => 100],
            ['name' => 'Reteica', 'tipo' => 'general', 'aplica_base' => true, 'aplica_iva' => false, 'divisor' => 1000],
            ['name' => 'Reteiva', 'tipo' => 'general', 'aplica_base' => false, 'aplica_iva' => true, 'divisor' => 100],
            ['name' => 'Fedepapa', 'tipo' => 'parafiscal', 'aplica_base' => true, 'aplica_iva' => false, 'divisor' => 100],
            ['name' => 'Asohofrucol', 'tipo' => 'parafiscal', 'aplica_base' => true, 'aplica_iva' => false, 'divisor' => 100],
            ['name' => 'Estampilla Magdalena', 'tipo' => 'territorial', 'aplica_base' => true, 'aplica_iva' => false, 'divisor' => 100],
        ];

        foreach ($retenciones as $retencion) {
            Retencion::updateOrCreate(['name' => $retencion['name']], $retencion);
        }
    }
}
