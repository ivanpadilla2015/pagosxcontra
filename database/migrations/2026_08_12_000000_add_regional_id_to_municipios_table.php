<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->foreignId('regional_id')->nullable()->constrained()->after('departamento');
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->dropForeign(['regional_id']);
            $table->dropColumn('regional_id');
        });
    }
};
