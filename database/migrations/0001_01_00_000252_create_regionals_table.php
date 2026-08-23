<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regionals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('director_id')->constrained()->onDelete('cascade');
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regionals');
    }
};
