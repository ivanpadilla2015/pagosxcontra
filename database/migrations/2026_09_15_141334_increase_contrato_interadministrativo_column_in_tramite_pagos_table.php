<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramite_pagos', function (Blueprint $table) {
            $table->string('contrato_interadministrativo', 255)->nullable()->default('N/A')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tramite_pagos', function (Blueprint $table) {
            $table->string('contrato_interadministrativo', 50)->nullable()->default('N/A')->change();
        });
    }
};
