<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_pagos', function (Blueprint $table) {
            $table->foreignId('movirubro_id')->nullable()->after('factura_id')->constrained()->nullOnDelete();
            $table->foreignId('uso_id')->nullable()->after('movirubro_id')->constrained()->nullOnDelete();
            $table->foreignId('rubro_id')->nullable()->after('uso_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_pagos', function (Blueprint $table) {
            $table->dropForeign(['movirubro_id']);
            $table->dropForeign(['uso_id']);
            $table->dropForeign(['rubro_id']);
            $table->dropColumn(['movirubro_id', 'uso_id', 'rubro_id']);
        });
    }
};
