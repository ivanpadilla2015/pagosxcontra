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
        Schema::create('reteica_tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained()->cascadeOnDelete();
            $table->decimal('porcentaje', 6, 2);
            $table->string('codigo_actividad', 10)->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'municipio_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reteica_tarifas');
    }
};
