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
        Schema::create('estampilla_tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retencion_id')->constrained()->cascadeOnDelete();
            $table->string('departamento');
            $table->string('tipo_adquisicion', 10)->nullable();
            $table->decimal('porcentaje', 6, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estampilla_tarifas');
    }
};
