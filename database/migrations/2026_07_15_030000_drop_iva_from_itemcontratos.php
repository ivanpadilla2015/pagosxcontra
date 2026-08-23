<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->dropColumn('iva');
        });
    }

    public function down(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->float('iva', 5, 2)->nullable()->after('unidad');
        });
    }
};
