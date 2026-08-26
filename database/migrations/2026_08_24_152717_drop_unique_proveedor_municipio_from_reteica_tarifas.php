<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropIndex('reteica_tarifas_proveedor_id_municipio_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->unique(['proveedor_id', 'municipio_id'], 'reteica_tarifas_proveedor_id_municipio_id_unique');
        });
    }
};
