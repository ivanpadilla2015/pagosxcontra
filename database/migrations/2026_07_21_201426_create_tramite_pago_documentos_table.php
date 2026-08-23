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
        Schema::create('tramite_pago_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_pago_id')->constrained('tramite_pagos')->cascadeOnDelete();
            $table->enum('tipo', ['soporte', 'expediente']);
            $table->string('nombre_documento', 255);
            $table->date('fecha')->nullable();
            $table->decimal('valor', 14, 2)->nullable();
            $table->integer('folio')->nullable();
            $table->boolean('reposa_expediente')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_pago_documentos');
    }
};
