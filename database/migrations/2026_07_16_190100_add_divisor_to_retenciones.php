<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retenciones', function (Blueprint $table) {
            $table->integer('divisor')->default(100)->after('aplica_iva');
        });
    }

    public function down(): void
    {
        Schema::table('retenciones', function (Blueprint $table) {
            $table->dropColumn('divisor');
        });
    }
};
