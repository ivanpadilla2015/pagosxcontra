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
        Schema::create('movirubros', function (Blueprint $table) {
            $table->id();
            $table->double('valor_rubro', 20, 2);
            $table->double('saldo_rubro', 20, 2);
            $table->string('dependencia_afectacion')->nullable();
            $table->foreignId('registro_id')->constrained();
            $table->foreignId('rubro_id')->constrained();
            $table->foreignId('contrato_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movirubros');
    }
};
