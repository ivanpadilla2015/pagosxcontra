<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropUnique('reteica_tarifas_municipio_id_tipo_adquisicion_unique');
            $table->foreignId('proveedor_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['proveedor_id', 'municipio_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
            $table->dropIndex('reteica_tarifas_proveedor_id_municipio_id_unique');
            $table->unique(['municipio_id', 'tipo_adquisicion']);
        });
    }
};
