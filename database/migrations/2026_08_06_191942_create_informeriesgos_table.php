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
        Schema::create('informeriesgos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50);
            $table->text('descripcion');
            $table->text('tratamiento');
            $table->string('responsable');
            $table->string('periodicidad');
            $table->foreignId('informe_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informeriesgos');
    }
};
