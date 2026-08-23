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
        Schema::create('retencion_tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retencion_id')->constrained()->cascadeOnDelete();
            $table->boolean('es_declarante')->nullable();
            $table->string('tipo_adquisicion', 10)->nullable();
            $table->boolean('es_agricola')->nullable();
            $table->decimal('porcentaje', 6, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retencion_tarifas');
    }
};
