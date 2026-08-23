<?php

namespace Database\Seeders;

use App\Models\Contrainter;
use Illuminate\Database\Seeder;

class ContrainterSeeder extends Seeder
{
    public function run(): void
    {
        Contrainter::firstOrCreate(
            ['detalle' => 'No Aplica'],
            [
                'concargo_a' => 'No Aplica',
                'plazoejecucion' => 'No Aplica',
            ]
        );
    }
}
