<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->foreignId('movirubro_id')->nullable()->after('producto_id')->constrained('movirubros')->nullOnDelete();
            $table->foreignId('rubro_id')->nullable()->after('movirubro_id')->constrained('rubros')->nullOnDelete();
            $table->double('valor_costo', 20, 2)->nullable()->after('rubro_id');
            $table->double('valor_iva', 20, 2)->nullable()->after('valor_costo');
            $table->double('valor_con_iva', 20, 2)->nullable()->after('valor_iva');
        });
    }

    public function down(): void
    {
        Schema::table('itemcontratos', function (Blueprint $table) {
            $table->dropForeign(['movirubro_id']);
            $table->dropForeign(['rubro_id']);
            $table->dropColumn(['movirubro_id', 'rubro_id', 'valor_costo', 'valor_iva', 'valor_con_iva']);
        });
    }
};
