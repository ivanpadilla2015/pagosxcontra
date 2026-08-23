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
        Schema::create('tramite_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Encabezado del pago
            $table->date('fecha_tramite');
            $table->integer('numero_pago')->default(1);
            $table->decimal('valor_pago_solicitado', 14, 2)->default(0);

            // Datos del contrato (campos calculados/copiados)
            $table->string('registro_presupuestal', 50)->nullable();
            $table->boolean('vigencia_actual')->default(true);
            $table->decimal('valor_inicial_contrato', 14, 2)->default(0);
            $table->decimal('valor_adiciones', 14, 2)->default(0);
            $table->decimal('valor_reducciones', 14, 2)->default(0);
            $table->decimal('valor_total_contrato', 14, 2)->default(0);
            $table->string('contrato_interadministrativo', 50)->nullable()->default('N/A');
            $table->date('fecha_legalizacion')->nullable();
            $table->date('fecha_finalizacion')->nullable();
            $table->decimal('porcentaje_ejecucion', 5, 2)->default(0);

            // Modificaciones del contrato
            $table->boolean('mod_adicion')->default(false);
            $table->boolean('mod_modificacion')->default(false);
            $table->boolean('mod_suspension')->default(false);
            $table->boolean('mod_prorroga')->default(false);
            $table->boolean('mod_cesion')->default(false);
            $table->text('novedades_contrato')->nullable();

            // Garantías - Póliza cumplimiento
            $table->string('poliza_cumplimiento_numero', 50)->nullable();
            $table->decimal('poliza_cumplimiento_valor', 14, 2)->nullable();
            $table->date('poliza_cumplimiento_inicio')->nullable();
            $table->date('poliza_cumplimiento_fin')->nullable();

            // Garantías - Póliza responsabilidad civil
            $table->string('poliza_rc_numero', 50)->nullable();
            $table->decimal('poliza_rc_valor', 14, 2)->nullable();
            $table->date('poliza_rc_inicio')->nullable();
            $table->date('poliza_rc_fin')->nullable();

            // Datos financieros
            $table->string('cuenta_bancaria_entidad', 100)->nullable();
            $table->string('numero_cuenta', 50)->nullable();
            $table->enum('tipo_cuenta', ['ahorro', 'corriente'])->default('ahorro');
            $table->enum('regimen_tributario', ['iva', 'no_iva', 'simple'])->default('iva');
            $table->enum('tipo_facturacion', ['electronica', 'cuenta_cobro'])->default('electronica');

            // Cumplimiento Ley 50/1990
            $table->boolean('cumple_ley_50')->default(false);
            $table->boolean('planilla_seguridad_social')->default(false);
            $table->boolean('certificacion_seguridad_social')->default(false);
            $table->boolean('certificacion_obligaciones_laborales')->default(false);
            $table->string('numero_planilla_ss', 50)->nullable();
            $table->string('ibc', 50)->nullable();
            $table->string('periodo_salud', 20)->nullable();
            $table->string('periodo_pension', 20)->nullable();

            // Aprobación
            $table->boolean('secop_ii')->default(false);
            $table->boolean('siif')->default(false);

            // Solo primer pago
            $table->boolean('cargar_rit_secop')->default(false);
            $table->boolean('cargar_rut_secop')->default(false);

            // Firmas
            $table->string('responsable_tramite', 100)->nullable();
            $table->string('cargo_responsable', 100)->nullable();
            $table->string('validacion_gestor', 100)->nullable();
            $table->string('cargo_gestor', 100)->nullable();
            $table->string('vb_directivo', 100)->nullable();
            $table->string('cargo_directivo', 100)->nullable();
            $table->bigInteger('cansecu_tramite')->default(0);
            $table->bigInteger('cansecu_infor')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_pagos');
    }
};
