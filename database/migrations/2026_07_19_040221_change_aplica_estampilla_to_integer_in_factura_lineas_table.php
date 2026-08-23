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
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropColumn('aplica_estampilla');
        });

        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->unsignedBigInteger('estampilla_retencion_id')->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropColumn('estampilla_retencion_id');
        });

        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->boolean('aplica_estampilla')->default(false)->after('cantidad');
        });
    }
};
