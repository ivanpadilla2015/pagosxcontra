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
        Schema::create('regimen_retencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regimen_tributario_id')->constrained('regimen_tributarios')->cascadeOnDelete();
            $table->foreignId('retencion_id')->constrained('retenciones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['regimen_tributario_id', 'retencion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regimen_retencion');
    }
};
