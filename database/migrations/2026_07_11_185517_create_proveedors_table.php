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
        Schema::create('proveedors', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit')->unique();
            $table->string('digver')->nullable(); // Se agrega nullable por si algunos NIT no lo tienen

            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();

            // Corregido de "repreleagal" a "representante_legal" por convención
            $table->string('representante_legal')->nullable();
           
            $table->foreignId('tipoper_id')->constrained();
            $table->foreignId('regimen_tributario_id')->constrained();
            $table->string('name_cuenta_bancaria')->nullable();
            $table->string('numero_cuenta')->nullable();
            $table->foreignId('tipocuenta_id')->constrained();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedors');
    }
};
