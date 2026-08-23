<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cansecu_infor')->default(0);
            $table->date('fecha');
            $table->foreignId('contrato_id')->constrained();
            $table->foreignId('tramite_pago_id')->nullable()->constrained()->nullOnDelete();
            $table->string('estado', 20)->default('abierto');
            $table->double('total_info',20,2);
            $table->double('saldo_viene',20,2);
            $table->string('porcentaje_cumplimiento');
            $table->string('mes_ejecucion',2);
            $table->string('corresponde_texto_periodo');
            $table->text('novedad')->nullable();
            $table->text('fiducia')->nullable();
            $table->text('infopersonal')->nullable();
            $table->text('infoaiu')->nullable();
            $table->text('anexos')->nullable();
            $table->text('recomendacion')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
                                    
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};
