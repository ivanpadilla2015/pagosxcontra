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
        Schema::create('informeobligacions', function (Blueprint $table) {
            $table->id();
            $table->string('numeral')->nullable();
            $table->text('obligacion_deta');
            $table->string('entregable');
            $table->string('confirmar')->nullable();
            $table->foreignId('informe_id')->constrained();
            $table->foreignId('contrato_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informeobligacions');
    }
};
