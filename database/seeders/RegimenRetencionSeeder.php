<?php

namespace Database\Seeders;

use App\Models\RegimenTributario;
use App\Models\Retencion;
use Illuminate\Database\Seeder;

class RegimenRetencionSeeder extends Seeder
{
    /**
     * Mapeo por defecto de qué retenciones aplica cada régimen tributario.
     *
     * Debe ejecutarse DESPUÉS de RegimenTributarioSeeder y RetencionSeeder.
     * Usa syncWithoutDetaching para no borrar asociaciones existentes.
     *
     * @return void
     */
    public function run()
    {
        $mapa = [
            'No Responsable de IVA' => ['Retefuente', 'Reteica'],
            'Régimen Simple'        => ['Reteiva'],
            'Responsable de IVA'    => ['Retefuente', 'Reteica', 'Reteiva'],
            'Gran Contribuyente'    => ['Retefuente', 'Reteica'],
            'Autorretenedor'        => [],
        ];

        foreach ($mapa as $nombreRegimen => $nombresRetenciones) {
            $regimen = RegimenTributario::where('name', $nombreRegimen)->first();

            if (! $regimen) {
                continue;
            }

            $ids = Retencion::whereIn('name', $nombresRetenciones)->pluck('id')->toArray();

            $regimen->retenciones()->sync($ids);
        }
    }
}
