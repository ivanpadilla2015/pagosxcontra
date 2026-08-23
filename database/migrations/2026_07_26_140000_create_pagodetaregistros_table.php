<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagodetaregistros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registro_id')->constrained()->cascadeOnDelete();
            $table->string('numero_reg', 50);
            $table->double('valor_reg', 20, 2);
            $table->date('fecha_reg');
            $table->boolean('estado')->default(1);
            $table->date('newplazoejecucion');
            $table->foreignId('tiporegistro_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagodetaregistros');
    }
};
