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
        Schema::table('retenciones', function (Blueprint $table) {
            $table->boolean('aplica_base')->default(false)->after('name');
            $table->boolean('aplica_iva')->default(false)->after('aplica_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retenciones', function (Blueprint $table) {
            $table->dropColumn(['aplica_base', 'aplica_iva']);
        });
    }
};
