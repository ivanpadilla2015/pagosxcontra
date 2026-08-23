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
        Schema::create('obligacions', function (Blueprint $table) {
            $table->id();
            $table->text('numeral');
            $table->text('obligacion_deta');
            $table->string('entregable');
            $table->foreignId('contrato_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligacions');
    }
};
