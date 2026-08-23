<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependencias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('regional_id')->nullable()->constrained('regionals')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias');
    }
};
