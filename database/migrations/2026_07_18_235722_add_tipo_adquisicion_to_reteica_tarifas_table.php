<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->unique(['proveedor_id', 'municipio_id', 'tipo_adquisicion'], 'reteica_prov_muni_tipo_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropIndex('reteica_prov_muni_tipo_UNIQUE');
        });
    }
};
