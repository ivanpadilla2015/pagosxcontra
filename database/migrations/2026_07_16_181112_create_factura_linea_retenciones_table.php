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
        Schema::create('factura_linea_retenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_linea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retencion_id')->constrained();
            $table->string('base_calculo', 10);
            $table->decimal('porcentaje_aplicado', 6, 2);
            $table->decimal('valor_retenido', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factura_linea_retenciones');
    }
};
