<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->double('valorprosiniva', 20, 2)->nullable()->change();
            $table->double('valoriva', 20, 2)->nullable()->change();
            $table->double('valorproconiva', 20, 2)->nullable()->change();
            $table->string('unidad', 8)->nullable()->change();
            $table->float('iva', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->double('valorprosiniva', 20, 2)->nullable(false)->change();
            $table->double('valoriva', 20, 2)->nullable(false)->change();
            $table->double('valorproconiva', 20, 2)->nullable(false)->change();
            $table->string('unidad', 8)->nullable(false)->change();
            $table->float('iva', 5, 2)->nullable(false)->change();
        });
    }
};
