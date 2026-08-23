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
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->string('numero_reg');
            $table->date('fecha_reg');
            $table->date('newplazoejecucion');
            $table->double('valor_reg', 20, 2);
            $table->boolean('estado')->default(1); //0 == inactivo  &&  1  == activo
            $table->foreignId('tiporegistro_id')->constrained();
            $table->foreignId('contrato_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
