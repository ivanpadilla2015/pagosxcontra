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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('numcontrato', 50)->unique();
            $table->date('fechacontrato');
            $table->date('fecha_inicio_contrato');
            $table->date('fecha_fin_contrato');
            $table->text('objetocontrato');
            $table->bigInteger('num_mes')->default(0); // para saber desde que mes enpezaron los pagos
            $table->bigInteger('cansecu_pagos')->default(0); // para llevar consecutivo de pagos
            $table->bigInteger('cansecu_infor')->default(0); // para llevar consecutivo de informes
            $table->bigInteger('cansecu_tramite')->default(0); // para llevar consecutivo de tramite
            $table->string('numero_poliza', 50)->nullable();
            $table->decimal('valor_poliza_asegurado', 10, 2)->nullable();
            $table->date('fecha_poliza_inicio')->nullable();
            $table->date('fecha_poliza_fin')->nullable();
            $table->string('sape_acreedor',20)->nullable();
            $table->string('orden_erp_sap')->nullable();
            $table->string('expediente_orfeo')->nullable();
            $table->foreignId('proveedor_id')->constrained();
            $table->foreignId('tipocontrato_id')->constrained();
            $table->foreignId('contrainter_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
