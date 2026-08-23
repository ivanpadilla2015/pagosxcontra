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
            $table->decimal('valor_con_iva', 14, 2)->default(0)->after('valor_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropColumn('valor_con_iva');
        });
    }
};
