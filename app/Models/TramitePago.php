<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TramitePago extends Model
{
    protected $fillable = [
        'contrato_id',
        'user_id',
        'fecha_tramite',
        'numero_pago',
        'valor_pago_solicitado',
        'registro_presupuestal',
        'vigencia_actual',
        'valor_inicial_contrato',
        'valor_adiciones',
        'valor_reducciones',
        'valor_total_contrato',
        'contrato_interadministrativo',
        'fecha_legalizacion',
        'fecha_finalizacion',
        'porcentaje_ejecucion',
        'mod_adicion',
        'mod_modificacion',
        'mod_suspension',
        'mod_prorroga',
        'mod_cesion',
        'novedades_contrato',
        'poliza_cumplimiento_numero',
        'poliza_cumplimiento_valor',
        'poliza_cumplimiento_inicio',
        'poliza_cumplimiento_fin',
        'poliza_rc_numero',
        'poliza_rc_valor',
        'poliza_rc_inicio',
        'poliza_rc_fin',
        'cuenta_bancaria_entidad',
        'numero_cuenta',
        'tipo_cuenta',
        'regimen_tributario',
        'tipo_facturacion',
        'cumple_ley_50',
        'planilla_seguridad_social',
        'certificacion_seguridad_social',
        'certificacion_obligaciones_laborales',
        'numero_planilla_ss',
        'ibc',
        'periodo_salud',
        'periodo_pension',
        'secop_ii',
        'siif',
        'cargar_rit_secop',
        'cargar_rut_secop',
        'responsable_tramite',
        'cargo_responsable',
        'validacion_gestor',
        'cargo_gestor',
        'vb_directivo',
        'cargo_directivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_tramite' => 'date',
            'fecha_legalizacion' => 'date',
            'fecha_finalizacion' => 'date',
            'poliza_cumplimiento_inicio' => 'date',
            'poliza_cumplimiento_fin' => 'date',
            'poliza_rc_inicio' => 'date',
            'poliza_rc_fin' => 'date',
            'valor_pago_solicitado' => 'decimal:2',
            'valor_inicial_contrato' => 'decimal:2',
            'valor_adiciones' => 'decimal:2',
            'valor_reducciones' => 'decimal:2',
            'valor_total_contrato' => 'decimal:2',
            'porcentaje_ejecucion' => 'decimal:2',
            'poliza_cumplimiento_valor' => 'decimal:2',
            'poliza_rc_valor' => 'decimal:2',
            'numero_pago' => 'integer',
            'vigencia_actual' => 'boolean',
            'mod_adicion' => 'boolean',
            'mod_modificacion' => 'boolean',
            'mod_suspension' => 'boolean',
            'mod_prorroga' => 'boolean',
            'mod_cesion' => 'boolean',
            'cumple_ley_50' => 'boolean',
            'planilla_seguridad_social' => 'boolean',
            'certificacion_seguridad_social' => 'boolean',
            'certificacion_obligaciones_laborales' => 'boolean',
            'secop_ii' => 'boolean',
            'siif' => 'boolean',
            'cargar_rit_secop' => 'boolean',
            'cargar_rut_secop' => 'boolean',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramitePagoDocumento::class);
    }

    public function documentosSoporte(): HasMany
    {
        return $this->hasMany(TramitePagoDocumento::class)->where('tipo', 'soporte');
    }

    public function documentosExpediente(): HasMany
    {
        return $this->hasMany(TramitePagoDocumento::class)->where('tipo', 'expediente');
    }
}
