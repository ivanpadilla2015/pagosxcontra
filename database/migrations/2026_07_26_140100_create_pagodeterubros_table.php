<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagodeterubros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movirubro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rubro_id')->constrained()->cascadeOnDelete();
            $table->double('valor_rubro', 20, 2);
            $table->double('saldo_rubro', 20, 2);
            $table->string('dependencia_afectacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagodeterubros');
    }
};
