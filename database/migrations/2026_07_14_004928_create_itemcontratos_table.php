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
        Schema::create('itemcontratos', function (Blueprint $table) {
            $table->id();
            $table->double('valorprosiniva',20,2);
            $table->double('valoriva',20,2);
            $table->double('valorproconiva',20,2);
            $table->string('unidad',8);
            $table->float('iva',5,2);
            $table->foreignId('contrato_id')->constrained();
            $table->foreignId('producto_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itemcontratos');
    }
};
