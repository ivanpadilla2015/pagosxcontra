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
        Schema::create('factura_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itemcontrato_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('producto_id')->constrained();
            $table->string('tipo_adquisicion', 10);
            $table->foreignId('municipio_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('valor_base', 14, 2);
            $table->decimal('valor_iva', 14, 2);
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factura_lineas');
    }
};
