<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movirubros', function (Blueprint $table) {
            $table->foreignId('movirubro_padre_id')->nullable()->after('contrato_id')->constrained('movirubros');
        });
    }

    public function down(): void
    {
        Schema::table('movirubros', function (Blueprint $table) {
            $table->dropForeign(['movirubro_padre_id']);
            $table->dropColumn('movirubro_padre_id');
        });
    }
};
