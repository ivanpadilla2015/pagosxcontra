<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informes', function (Blueprint $table) {
            // Columnas ya creadas en la migración create_informes_table
        });
    }

    public function down(): void
    {
        Schema::table('informes', function (Blueprint $table) {
            //
        });
    }
};
