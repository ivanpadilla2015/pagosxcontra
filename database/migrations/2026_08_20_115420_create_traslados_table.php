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
        Schema::create('traslados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos');
            $table->foreignId('movirubro_origen_id')->constrained('movirubros');
            $table->foreignId('movirubro_destino_id')->constrained('movirubros');
            $table->decimal('valor', 20, 2);
            $table->enum('estado', ['propuesto', 'aprobado', 'rechazado'])->default('propuesto');
            $table->foreignId('user_propone_id')->constrained('users');
            $table->foreignId('user_aprueba_id')->nullable()->constrained('users');
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registro_id')->nullable()->constrained('registros');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};
