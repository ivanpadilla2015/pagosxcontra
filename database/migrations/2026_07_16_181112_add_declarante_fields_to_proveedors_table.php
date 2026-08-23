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
        Schema::table('proveedors', function (Blueprint $table) {
            $table->boolean('es_declarante')->default(false)->after('tiene_excepcion_retenciones');
            $table->string('codigo_actividad_economica', 10)->nullable()->after('es_declarante');
            $table->string('descripcion_actividad')->nullable()->after('codigo_actividad_economica');
        });
    }

    public function down(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            $table->dropColumn(['es_declarante', 'codigo_actividad_economica', 'descripcion_actividad']);
        });
    }
};
