<?php

namespace Database\Seeders;

use App\Models\Retencion;
use App\Models\RetencionTarifa;
use App\Models\EstampillaTarifa;
use Illuminate\Database\Seeder;

class RetencionTarifasSeeder extends Seeder
{
    public function run()
    {
        $redefuente = Retencion::where('name', 'Retefuente')->first();
        $reteica = Retencion::where('name', 'Reteica')->first();
        $reteiva = Retencion::where('name', 'Reteiva')->first();
        $fedepapa = Retencion::where('name', 'Fedepapa')->first();
        $asohofrucol = Retencion::where('name', 'Asohofrucol')->first();
        $estampilla = Retencion::where('name', 'Estampilla Magdalena')->first();

        // Retencion tarifas
        $tarifas = [
            // Retefuente
            ['retencion_id' => $redefuente->id, 'es_declarante' => false, 'tipo_adquisicion' => null, 'es_agricola' => null, 'porcentaje' => 3.5],
            ['retencion_id' => $redefuente->id, 'es_declarante' => true, 'tipo_adquisicion' => 'servicio', 'es_agricola' => null, 'porcentaje' => 4],
            ['retencion_id' => $redefuente->id, 'es_declarante' => true, 'tipo_adquisicion' => 'bien', 'es_agricola' => true, 'porcentaje' => 1.5],
            ['retencion_id' => $redefuente->id, 'es_declarante' => true, 'tipo_adquisicion' => 'bien', 'es_agricola' => false, 'porcentaje' => 2.5],
            // Reteiva
            ['retencion_id' => $reteiva->id, 'es_declarante' => null, 'tipo_adquisicion' => null, 'es_agricola' => null, 'porcentaje' => 15],
            // Reteica (bien)
            ['retencion_id' => $reteica->id, 'es_declarante' => null, 'tipo_adquisicion' => 'bien', 'es_agricola' => null, 'porcentaje' => 0.5],
            // Fedepapa
            ['retencion_id' => $fedepapa->id, 'es_declarante' => null, 'tipo_adquisicion' => null, 'es_agricola' => null, 'porcentaje' => 1],
            // Asohofrucol
            ['retencion_id' => $asohofrucol->id, 'es_declarante' => null, 'tipo_adquisicion' => null, 'es_agricola' => null, 'porcentaje' => 1],
        ];

        foreach ($tarifas as $tarifa) {
            RetencionTarifa::updateOrCreate(
                ['retencion_id' => $tarifa['retencion_id'], 'es_declarante' => $tarifa['es_declarante'], 'tipo_adquisicion' => $tarifa['tipo_adquisicion'], 'es_agricola' => $tarifa['es_agricola']],
                ['porcentaje' => $tarifa['porcentaje']]
            );
        }

        // Estampilla tarifas
        EstampillaTarifa::updateOrCreate(
            ['retencion_id' => $estampilla->id, 'departamento' => 'Magdalena', 'tipo_adquisicion' => null],
            ['porcentaje' => 2]
        );
    }
}
