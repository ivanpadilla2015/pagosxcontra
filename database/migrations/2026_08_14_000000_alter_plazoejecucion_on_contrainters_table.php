<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrainters', function (Blueprint $table) {
            $table->string('plazoejecucion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contrainters', function (Blueprint $table) {
            $table->date('plazoejecucion')->nullable()->change();
        });
    }
};
