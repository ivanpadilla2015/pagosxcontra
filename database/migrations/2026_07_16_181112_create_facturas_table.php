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
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained();
            $table->foreignId('contrato_id')->constrained();
            $table->string('numero');
            $table->date('fecha');
            $table->string('estado')->default('borrador');
            $table->foreignId('municipio_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_iva', 14, 2)->default(0);
            $table->decimal('total_retenciones', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['proveedor_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
