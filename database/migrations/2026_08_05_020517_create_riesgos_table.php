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
        Schema::create('riesgos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->nullable();
            $table->text('descripcion');
            $table->text('tratamiento');
            $table->string('responsable');
            $table->string('periodicidad')->nullable();
            $table->foreignId('contrato_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riesgos');
    }
};
