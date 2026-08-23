<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->integer('cansecu_infor')->default(0)->after('contrato_id');
            $table->bigInteger('cansecu_tramite')->default(0)->after('cansecu_infor');
            $table->bigInteger('cansecu_pagos')->default(0)->after('cansecu_tramite');
            
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('cansecu_infor');
            $table->dropColumn('cansecu_tramite');
            $table->dropColumn('cansecu_pagos');
        });
    }
};
