<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        $this->call([
            PermissionSeeder::class,
            DashboardTableSeeder::class,
            TipoperSeeder::class,
            RegimenTributarioSeeder::class,
            TipocuentaSeeder::class,
            TipocontratoSeeder::class,
            RetencionSeeder::class,
            RegimenRetencionSeeder::class,
            RetencionTarifasSeeder::class,
            MunicipioSeeder::class,
            DependenciaSeeder::class,
            DirectorPresupuestoRegionalSeeder::class,
            ContrainterSeeder::class,
            RubrosUsosSeeder::class,
            TiporegistroSeeder::class,
            ProductoSeeder::class,
            PlantillaDocumentoSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
