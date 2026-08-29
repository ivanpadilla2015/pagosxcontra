<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->boolean('es_ajuste')->default(false)->after('estampilla_retencion_id');
            $table->decimal('porcentaje_iva', 5, 2)->nullable()->after('es_ajuste');
        });
    }

    public function down(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropColumn(['es_ajuste', 'porcentaje_iva']);
        });
    }
};
