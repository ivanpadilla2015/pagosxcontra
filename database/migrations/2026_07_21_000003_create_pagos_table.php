<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50);
            $table->date('fecha');
            $table->foreignId('contrato_id')->constrained();
            $table->foreignId('informe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tramite_pago_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('estado', 20)->default('abierto');
            $table->date('fecha_cierre')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
