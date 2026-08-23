<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->dropColumn(['valorprosiniva', 'valoriva', 'valorproconiva']);
        });
    }

    public function down(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->double('valorprosiniva', 20, 2)->nullable()->after('id');
            $table->double('valoriva', 20, 2)->nullable()->after('valorprosiniva');
            $table->double('valorproconiva', 20, 2)->nullable()->after('valoriva');
        });
    }
};
