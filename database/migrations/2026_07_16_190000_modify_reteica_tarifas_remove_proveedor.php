<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
            $table->dropIndex('reteica_tarifas_proveedor_id_municipio_id_unique');
            $table->string('tipo_adquisicion', 10)->nullable()->after('municipio_id');
            $table->unique(['municipio_id', 'tipo_adquisicion']);
        });
    }

    public function down(): void
    {
        Schema::table('reteica_tarifas', function (Blueprint $table) {
            $table->dropIndex('reteica_tarifas_municipio_id_tipo_adquisicion_unique');
            $table->dropColumn('tipo_adquisicion');
            $table->foreignId('proveedor_id')->constrained()->cascadeOnDelete();
            $table->unique(['proveedor_id', 'municipio_id']);
        });
    }
};
