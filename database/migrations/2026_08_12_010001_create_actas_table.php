<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20);
            $table->foreignId('factura_id')->constrained()->onDelete('cascade');
            $table->foreignId('contrato_id')->constrained()->onDelete('cascade');
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->string('nombre_entrega');
            $table->string('cargo_entrega');
            $table->string('en_calidad_de');
            $table->date('fecha');
            $table->time('hora');
            $table->string('inspeccion_visual')->nullable();
            $table->string('informes_laboratorio')->nullable();
            $table->string('certificacion_expedida')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas');
    }
};
