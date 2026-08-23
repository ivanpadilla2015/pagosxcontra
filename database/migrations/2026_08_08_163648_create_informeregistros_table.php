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
        Schema::create('informeregistros', function (Blueprint $table) {
            $table->id();
            $table->string('numero_reg',10);
            $table->double('valor_reg', 20, 2);
            $table->date('fecha_reg');
            $table->date('newplazoejecucion');
                       
            $table->foreignId('tiporegistro_id')->constrained();
            $table->foreignId('informe_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informeregistros');
    }
};
