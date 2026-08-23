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
        Schema::table('regionals', function (Blueprint $table) {
            $table->string('firma_nombre_coord_admin', 255)->nullable()->after('municipio_id');
            $table->string('firma_cargo_admin', 255)->nullable()->after('firma_nombre_coord_admin');
            $table->string('firma_nombre_coord_contrato', 255)->nullable()->after('firma_cargo_admin');
            $table->string('firma_cargo_contrato', 255)->nullable()->after('firma_nombre_coord_contrato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regionals', function (Blueprint $table) {
            $table->dropColumn([
                'firma_nombre_coord_admin',
                'firma_cargo_admin',
                'firma_nombre_coord_contrato',
                'firma_cargo_contrato',
            ]);
        });
    }
};
