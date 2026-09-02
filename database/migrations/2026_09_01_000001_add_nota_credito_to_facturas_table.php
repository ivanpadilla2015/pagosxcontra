<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('nota_credito')->nullable()->after('dependencia_id');
            $table->decimal('nota_credito_valor', 14, 2)->nullable()->after('nota_credito');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['nota_credito', 'nota_credito_valor']);
        });
    }
};
