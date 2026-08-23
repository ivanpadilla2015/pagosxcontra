<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\TramitePago;
use App\Models\TramitePagoDocumento;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\PlantillaDocumento;
use App\Traits\FiltrablePorRegional;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use FiltrablePorRegional;

    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'desc';

    // Modal states
    public $tramite_pago_id;
    public $showFormModal = false;
    public $showDeleteModal = false;
    public $confirmDeleteId = null;
    public $editing = false;

    // Campo principal
    public $contrato_id;
    public $contrato = null;
    public $numcontrato = '';
    public $contrato_encontrado = false;
    public $resultados_busqueda = [];

    // Encabezado del pago
    public $fecha_tramite;
    public $numero_pago = 1;
    public $valor_pago_solicitado = 0;
    public $siguiente_informe = 0;

    // Datos del contrato
    public $registro_presupuestal;
    public $vigencia_actual = true;
    public $valor_inicial_contrato = 0;
    public $valor_adiciones = 0;
    public $valor_reducciones = 0;
    public $valor_total_contrato = 0;
    public $contrato_interadministrativo = 'N/A';
    public $fecha_legalizacion;
    public $fecha_finalizacion;
    public $porcentaje_ejecucion = 0;

    // Modificaciones
    public $mod_adicion = false;
    public $mod_modificacion = false;
    public $mod_suspension = false;
    public $mod_prorroga = false;
    public $mod_cesion = false;
    public $novedades_contrato;

    // Garantías
    public $poliza_cumplimiento_numero;
    public $poliza_cumplimiento_valor;
    public $poliza_cumplimiento_inicio;
    public $poliza_cumplimiento_fin;
    public $poliza_rc_numero;
    public $poliza_rc_valor;
    public $poliza_rc_inicio;
    public $poliza_rc_fin;

    // Datos financieros
    public $cuenta_bancaria_entidad;
    public $numero_cuenta;
    public $tipo_cuenta = 'ahorro';
    public $regimen_tributario = 'iva';
    public $tipo_facturacion = 'electronica';

    // Cumplimiento
    public $cumple_ley_50 = false;
    public $planilla_seguridad_social = false;
    public $certificacion_seguridad_social = false;
    public $certificacion_obligaciones_laborales = false;
    public $numero_planilla_ss;
    public $ibc;
    public $periodo_salud;
    public $periodo_pension;

    // Aprobación
    public $secop_ii = false;
    public $siif = false;

    // Solo primer pago
    public $cargar_rit_secop = false;
    public $cargar_rut_secop = false;

    // Firmas
    public $responsable_tramite;
    public $cargo_responsable;
    public $validacion_gestor;
    public $cargo_gestor;
    public $vb_directivo;
    public $cargo_directivo;

    // Error interno del modal
    public $modalError = '';

    // Verificación de estado del pago
    public $pagoEstado = null;

    // Documentos soporte
    public $documentos_soporte = [];
    public $new_doc_soporte_nombre = '';
    public $new_doc_soporte_fecha = '';
    public $new_doc_soporte_valor = '';
    public $new_doc_soporte_folio = '';
    public $guardar_como_plantilla_soporte = false;

    // Documentos expediente
    public $documentos_expediente = [];
    public $new_doc_exp_nombre = '';
    public $new_doc_exp_fecha = '';
    public $new_doc_exp_folio = '';
    public $new_doc_exp_reposa = false;
    public $guardar_como_plantilla_exp = false;

    #[Computed]
    public function tramitePagos()
    {
        return TramitePago::with(['contrato.proveedor'])
            ->whereHas('contrato', fn ($q) => $q->where('numcontrato', 'like', '%' . $this->search . '%'))
            ->orWhereHas('contrato.proveedor', fn ($q) => $q->where('nombre', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function contratos()
    {
        return Contrato::with(['proveedor'])->orderBy('numcontrato')->get();
    }

    #[Computed]
    public function facturasDelTramite()
    {
        if (! $this->contrato_id || ! $this->numero_pago) {
            return collect();
        }

        return \App\Models\Factura::query()
            ->join('detalle_pagos', 'detalle_pagos.factura_id', '=', 'facturas.id')
            ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.contrato_id', $this->contrato_id)
            ->where('pagos.cansecu_tramite', $this->numero_pago)
            ->where('pagos.estado', 'cerrado')
            ->select('facturas.*')
            ->distinct()
            ->get();
    }

    #[Computed]
    public function fechaPrimerPago()
    {
        if (! $this->contrato_id || ! $this->numero_pago) {
            return null;
        }

        $pago = \App\Models\Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->where('estado', 'cerrado')
            ->orderBy('fecha')
            ->first();

        return $pago?->fecha?->format('Y-m-d');
    }

    #[Computed]
    public function valorPrimerPago()
    {
        if (! $this->contrato_id || ! $this->numero_pago) {
            return null;
        }

        return \App\Models\Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->where('estado', 'cerrado')
            ->sum('valor_total');
    }

    #[Computed]
    public function totalFacturasTramite()
    {
        if (! $this->contrato_id || ! $this->numero_pago) {
            return 0;
        }

        $facturaIds = \App\Models\DetallePago::query()
            ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.contrato_id', $this->contrato_id)
            ->where('pagos.cansecu_tramite', $this->numero_pago)
            ->where('pagos.estado', 'cerrado')
            ->pluck('factura_id')
            ->unique();

        return (float) \App\Models\Factura::whereIn('id', $facturaIds)
            ->sum(DB::raw('subtotal + total_iva'));
    }

    public function buscarContrato()
    {
        if (strlen($this->numcontrato) < 2) {
            $this->resultados_busqueda = [];
            $this->contrato_encontrado = false;
            return;
        }

        $this->resultados_busqueda = Contrato::with(['proveedor'])
            ->where('numcontrato', 'like', '%' . $this->numcontrato . '%')
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function seleccionarContrato($contratoId)
    {
        $contrato = Contrato::with(['proveedor', 'contrainter', 'registros'])->find($contratoId);
        if ($contrato) {
            $this->contrato_id = $contrato->id;
            $this->contrato = $contrato;
            $this->numcontrato = $contrato->numcontrato;
            $this->contrato_encontrado = true;
            $this->resultados_busqueda = [];

            $this->valor_inicial_contrato = $contrato->valorTotal;
            $this->fecha_legalizacion = $contrato->fechacontrato?->format('Y-m-d');
            $this->fecha_finalizacion = $contrato->fecha_fin_contrato?->format('Y-m-d');
            $this->contrato_interadministrativo = $contrato->contrainter->detalle ?? 'N/A';
            $this->registro_presupuestal = $contrato->registros->pluck('numero_reg')->implode(', ');
            $this->poliza_cumplimiento_numero = $contrato->numero_poliza;
            $this->poliza_cumplimiento_valor = $contrato->valor_poliza_asegurado;
            $this->poliza_cumplimiento_inicio = $contrato->fecha_poliza_inicio?->format('Y-m-d');
            $this->poliza_cumplimiento_fin = $contrato->fecha_poliza_fin?->format('Y-m-d');
            $this->cuenta_bancaria_entidad = $contrato->proveedor->name_cuenta_bancaria ?? '';
            $this->numero_cuenta = $contrato->proveedor->numero_cuenta ?? '';
            $this->valor_total_contrato = $contrato->valorTotal;
            $this->porcentaje_ejecucion = $contrato->valorTotal > 0
                ? round((($contrato->valorTotal - $contrato->saldo) / $contrato->valorTotal) * 100, 2)
                : 0;

            $this->numero_pago = $contrato->cansecu_tramite + 1;
            $this->siguiente_informe = $contrato->cansecu_infor + 1;
            $this->fecha_tramite = now()->format('Y-m-d');

            $user = auth()->user();
            if ($user && $user->regional) {
                $this->responsable_tramite = $user->name;
                $this->cargo_responsable = $user->cargo ?? '';
                $this->validacion_gestor = $user->regional->firma_nombre_coord_contrato;
                $this->cargo_gestor = $user->regional->firma_cargo_contrato;
                $this->vb_directivo = $user->regional->firma_nombre_coord_admin;
                $this->cargo_directivo = $user->regional->firma_cargo_admin;
            }

            $this->verificarEstadoPago();

            // Suma de facturas sin retenciones del trámite (solo pagos confirmados)
            $facturaIds = \App\Models\DetallePago::query()
                ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
                ->where('pagos.contrato_id', $contrato->id)
                ->where('pagos.cansecu_tramite', $this->numero_pago)
                ->where('pagos.estado', 'cerrado')
                ->pluck('factura_id')
                ->unique();

            $this->valor_pago_solicitado = \App\Models\Factura::whereIn('id', $facturaIds)
                ->sum(DB::raw('subtotal + total_iva'));
        }
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingNumeroPago()
    {
        $this->verificarEstadoPago();
    }

    public function verificarEstadoPago()
    {
        $this->pagoEstado = null;

        if (! $this->contrato_id || ! $this->numero_pago) {
            return;
        }

        $pago = \App\Models\Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->first();

        if ($pago) {
            $this->pagoEstado = $pago->estado;
        }
    }

    public function create()
    {
        $this->resetInputFields();
        $this->editing = false;
        $this->modalError = '';

        // Pre-cargar documentos de la plantilla GF-FO-36
        $plantillaSoporte = PlantillaDocumento::where('tipo', 'soporte')->orderBy('orden')->get();
        $plantillaExpediente = PlantillaDocumento::where('tipo', 'expediente')->orderBy('orden')->get();

        $this->documentos_soporte = array_values($plantillaSoporte->map(fn ($p) => [
            'nombre_documento' => $p->nombre_documento,
            'fecha' => null,
            'valor' => null,
            'folio' => null,
            'tipo' => 'soporte',
        ])->toArray());

        $this->documentos_expediente = array_values($plantillaExpediente->map(fn ($p) => [
            'nombre_documento' => $p->nombre_documento,
            'fecha' => null,
            'folio' => null,
            'reposa_expediente' => true,
            'tipo' => 'expediente',
        ])->toArray());

        $this->showFormModal = true;
    }

    public function store()
    {
        $this->modalError = '';
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'fecha_tramite' => 'required|date',
            'numero_pago' => 'required|integer|min:1',
            'valor_pago_solicitado' => 'required|numeric|min:0',
        ]);

        // Verificar que exista un pago confirmado (cerrado) para este consecutivo
        $pagoConfirmado = Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->where('estado', 'cerrado')
            ->whereNull('tramite_pago_id')
            ->first();

        if (!$pagoConfirmado) {
            $pagoExiste = Pago::where('contrato_id', $this->contrato_id)
                ->where('cansecu_tramite', $this->numero_pago)
                ->first();

            if ($pagoExiste && $pagoExiste->estado !== 'cerrado') {
                $this->modalError = 'El pago N° ' . $pagoExiste->numero . ' está en estado "' . $pagoExiste->estado . '". Debe confirmar el pago antes de crear su trámite.';
            } else {
                $this->modalError = 'No se encontró un pago confirmado para el trámite N° ' . $this->numero_pago . '.';
            }
            return;
        }

        $tramite = TramitePago::create($this->getFormData() + ['user_id' => auth()->id()]);

        // Pre-llenar documentos soporte con datos de facturas y migos
        $this->llenarDocumentosConFacturas();

        $this->saveDocumentos($tramite);

        // Vincular trámite con el pago confirmado
        $pagoConfirmado->update(['tramite_pago_id' => $tramite->id]);

        Contrato::where('id', $this->contrato_id)->update(['cansecu_tramite' => $this->numero_pago]);

        session()->flash('message', 'Trámite de pago creado exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function edit($id)
    {
        $tramite = TramitePago::with(['documentos', 'contrato'])->findOrFail($id);
        $this->tramite_pago_id = $tramite->id;
        $this->contrato_id = $tramite->contrato_id;
        $this->contrato = $tramite->contrato;
        $this->numcontrato = $tramite->contrato->numcontrato ?? '';
        $this->contrato_encontrado = true;
        $this->siguiente_informe = $tramite->contrato->cansecu_infor + 1;
        $this->fecha_tramite = $tramite->fecha_tramite?->format('Y-m-d');
        $this->numero_pago = $tramite->numero_pago;
        $this->valor_pago_solicitado = $tramite->valor_pago_solicitado;
        $this->registro_presupuestal = $tramite->registro_presupuestal;
        $this->vigencia_actual = $tramite->vigencia_actual;
        $this->valor_inicial_contrato = $tramite->valor_inicial_contrato;
        $this->valor_adiciones = $tramite->valor_adiciones;
        $this->valor_reducciones = $tramite->valor_reducciones;
        $this->valor_total_contrato = $tramite->valor_total_contrato;
        $this->contrato_interadministrativo = $tramite->contrato_interadministrativo;
        $this->fecha_legalizacion = $tramite->fecha_legalizacion?->format('Y-m-d');
        $this->fecha_finalizacion = $tramite->fecha_finalizacion?->format('Y-m-d');
        $this->porcentaje_ejecucion = $tramite->porcentaje_ejecucion;
        $this->mod_adicion = $tramite->mod_adicion;
        $this->mod_modificacion = $tramite->mod_modificacion;
        $this->mod_suspension = $tramite->mod_suspension;
        $this->mod_prorroga = $tramite->mod_prorroga;
        $this->mod_cesion = $tramite->mod_cesion;
        $this->novedades_contrato = $tramite->novedades_contrato;
        $this->poliza_cumplimiento_numero = $tramite->poliza_cumplimiento_numero;
        $this->poliza_cumplimiento_valor = $tramite->poliza_cumplimiento_valor;
        $this->poliza_cumplimiento_inicio = $tramite->poliza_cumplimiento_inicio?->format('Y-m-d');
        $this->poliza_cumplimiento_fin = $tramite->poliza_cumplimiento_fin?->format('Y-m-d');
        $this->poliza_rc_numero = $tramite->poliza_rc_numero;
        $this->poliza_rc_valor = $tramite->poliza_rc_valor;
        $this->poliza_rc_inicio = $tramite->poliza_rc_inicio?->format('Y-m-d');
        $this->poliza_rc_fin = $tramite->poliza_rc_fin?->format('Y-m-d');
        $this->cuenta_bancaria_entidad = $tramite->cuenta_bancaria_entidad;
        $this->numero_cuenta = $tramite->numero_cuenta;
        $this->tipo_cuenta = $tramite->tipo_cuenta;
        $this->regimen_tributario = $tramite->regimen_tributario;
        $this->tipo_facturacion = $tramite->tipo_facturacion;
        $this->cumple_ley_50 = $tramite->cumple_ley_50;
        $this->planilla_seguridad_social = $tramite->planilla_seguridad_social;
        $this->certificacion_seguridad_social = $tramite->certificacion_seguridad_social;
        $this->certificacion_obligaciones_laborales = $tramite->certificacion_obligaciones_laborales;
        $this->numero_planilla_ss = $tramite->numero_planilla_ss;
        $this->ibc = $tramite->ibc;
        $this->periodo_salud = $tramite->periodo_salud;
        $this->periodo_pension = $tramite->periodo_pension;
        $this->secop_ii = $tramite->secop_ii;
        $this->siif = $tramite->siif;
        $this->cargar_rit_secop = $tramite->cargar_rit_secop;
        $this->cargar_rut_secop = $tramite->cargar_rut_secop;
        $this->responsable_tramite = $tramite->responsable_tramite;
        $this->cargo_responsable = $tramite->cargo_responsable;
        $this->validacion_gestor = $tramite->validacion_gestor;
        $this->cargo_gestor = $tramite->cargo_gestor;
        $this->vb_directivo = $tramite->vb_directivo;
        $this->cargo_directivo = $tramite->cargo_directivo;

        $this->documentos_soporte = array_values($tramite->documentosSoporte()->orderBy('id')->get()->map(fn ($doc) => [
            'id' => $doc->id,
            'nombre_documento' => $doc->nombre_documento,
            'fecha' => $doc->fecha?->format('Y-m-d'),
            'valor' => $doc->valor,
            'folio' => $doc->folio,
        ])->toArray());

        // Limpiar nombres de documentos soporte: quitar números duplicados que se muestran dinámicamente en el Blade
        if (isset($this->documentos_soporte[1])) {
            $this->documentos_soporte[1]['nombre_documento'] = preg_replace('/\s+[\d,\s]+$/', '', $this->documentos_soporte[1]['nombre_documento']);
        }
        if (isset($this->documentos_soporte[4])) {
            $this->documentos_soporte[4]['nombre_documento'] = preg_replace('/\s+\d+(?:\s*,\s*\d+)*$/', '', $this->documentos_soporte[4]['nombre_documento']);
        }

        $this->documentos_expediente = array_values($tramite->documentosExpediente()->orderBy('id')->get()->map(fn ($doc) => [
            'id' => $doc->id,
            'nombre_documento' => $doc->nombre_documento,
            'reposa_expediente' => $doc->reposa_expediente,
            'fecha' => $doc->fecha?->format('Y-m-d'),
            'folio' => $doc->folio,
        ])->toArray());

        // Limpiar nombre del documento index 8: quitar número duplicado
        if (isset($this->documentos_expediente[8])) {
            $this->documentos_expediente[8]['nombre_documento'] = preg_replace('/\s+\d+$/', '', $this->documentos_expediente[8]['nombre_documento']);
        }

        $this->editing = true;
        $this->showFormModal = true;
    }

    public function update()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'numcontrato' => 'required|string',
            'fecha_tramite' => 'required|date',
            'numero_pago' => 'required|integer|min:1',
            'valor_pago_solicitado' => 'required|numeric|min:0',
        ]);

        $tramite = TramitePago::findOrFail($this->tramite_pago_id);
        $tramite->update($this->getFormData());

        // Pre-llenar documentos soporte con datos de facturas y migos
        $this->llenarDocumentosConFacturas();

        // Reemplazar documentos
        $tramite->documentos()->delete();
        $this->saveDocumentos($tramite);

        // Vincular trámite con el pago confirmado
        $pagoConfirmado = Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->where('estado', 'cerrado')
            ->whereNull('tramite_pago_id')
            ->first();

        if ($pagoConfirmado) {
            $pagoConfirmado->update(['tramite_pago_id' => $tramite->id]);
        }

        session()->flash('message', 'Trámite de pago actualizado exitosamente.');
        $this->showFormModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function confirmDelete($id)
    {
        $this->confirmDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        $tramite = TramitePago::findOrFail($this->confirmDeleteId);

        // Desvincular pago asociado
        Pago::where('tramite_pago_id', $tramite->id)->update(['tramite_pago_id' => null]);

        // Eliminar documentos asociados
        $tramite->documentos()->delete();

        // Decrementar cansecu_tramite del contrato
        $contrato = Contrato::find($tramite->contrato_id);
        if ($contrato && $contrato->cansecu_tramite > 0) {
            $contrato->update(['cansecu_tramite' => $contrato->cansecu_tramite - 1]);
        }

        $tramite->delete();

        session()->flash('message', 'Trámite de pago eliminado exitosamente.');
        $this->confirmDeleteId = null;
        $this->showDeleteModal = false;
    }

    // Documentos soporte methods
    public function addDocumentoSoporte()
    {
        $this->validate([
            'new_doc_soporte_nombre' => 'required|string|max:255',
        ]);

        $this->documentos_soporte[] = [
            'nombre_documento' => $this->new_doc_soporte_nombre,
            'fecha' => $this->new_doc_soporte_fecha ?: null,
            'valor' => $this->new_doc_soporte_valor ?: null,
            'folio' => $this->new_doc_soporte_folio ?: null,
            'tipo' => 'soporte',
        ];

        if ($this->guardar_como_plantilla_soporte) {
            $existe = PlantillaDocumento::where('tipo', 'soporte')
                ->where('nombre_documento', $this->new_doc_soporte_nombre)
                ->exists();

            if (! $existe) {
                $maxOrden = PlantillaDocumento::where('tipo', 'soporte')->max('orden') ?? 0;
                PlantillaDocumento::create([
                    'tipo' => 'soporte',
                    'nombre_documento' => $this->new_doc_soporte_nombre,
                    'orden' => $maxOrden + 1,
                ]);
            }
        }

        $this->new_doc_soporte_nombre = '';
        $this->new_doc_soporte_fecha = '';
        $this->new_doc_soporte_valor = '';
        $this->new_doc_soporte_folio = '';
        $this->guardar_como_plantilla_soporte = false;
    }

    public function removeDocumentoSoporte($index)
    {
        unset($this->documentos_soporte[$index]);
        $this->documentos_soporte = array_values($this->documentos_soporte);
    }

    // Documentos expediente methods
    public function addDocumentoExpediente()
    {
        $this->validate([
            'new_doc_exp_nombre' => 'required|string|max:255',
        ]);

        $this->documentos_expediente[] = [
            'nombre_documento' => $this->new_doc_exp_nombre,
            'fecha' => $this->new_doc_exp_fecha ?: null,
            'folio' => $this->new_doc_exp_folio ?: null,
            'reposa_expediente' => $this->new_doc_exp_reposa,
            'tipo' => 'expediente',
        ];

        if ($this->guardar_como_plantilla_exp) {
            $existe = PlantillaDocumento::where('tipo', 'expediente')
                ->where('nombre_documento', $this->new_doc_exp_nombre)
                ->exists();

            if (! $existe) {
                $maxOrden = PlantillaDocumento::where('tipo', 'expediente')->max('orden') ?? 0;
                PlantillaDocumento::create([
                    'tipo' => 'expediente',
                    'nombre_documento' => $this->new_doc_exp_nombre,
                    'orden' => $maxOrden + 1,
                ]);
            }
        }

        $this->new_doc_exp_nombre = '';
        $this->new_doc_exp_fecha = '';
        $this->new_doc_exp_folio = '';
        $this->new_doc_exp_reposa = false;
        $this->guardar_como_plantilla_exp = false;
    }

    public function removeDocumentoExpediente($index)
    {
        unset($this->documentos_expediente[$index]);
        $this->documentos_expediente = array_values($this->documentos_expediente);
    }

    public function closeModal()
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->editing = false;
        $this->resetInputFields();
    }

    public function updatedShowFormModal($value)
    {
        if (! $value) {
            $this->editing = false;
        }
    }

    public function updatedShowDeleteModal($value)
    {
        if (! $value) {
            $this->confirmDeleteId = null;
        }
    }

    private function getFormData(): array
    {
        return [
            'contrato_id' => $this->contrato_id,
            'fecha_tramite' => $this->emptyToNull($this->fecha_tramite),
            'numero_pago' => $this->numero_pago,
            'valor_pago_solicitado' => $this->emptyToNull($this->valor_pago_solicitado),
            'registro_presupuestal' => $this->emptyToNull($this->registro_presupuestal),
            'vigencia_actual' => $this->vigencia_actual,
            'valor_inicial_contrato' => $this->emptyToNull($this->valor_inicial_contrato),
            'valor_adiciones' => $this->emptyToNull($this->valor_adiciones),
            'valor_reducciones' => $this->emptyToNull($this->valor_reducciones),
            'valor_total_contrato' => $this->emptyToNull($this->valor_total_contrato),
            'contrato_interadministrativo' => $this->emptyToNull($this->contrato_interadministrativo),
            'fecha_legalizacion' => $this->emptyToNull($this->fecha_legalizacion),
            'fecha_finalizacion' => $this->emptyToNull($this->fecha_finalizacion),
            'porcentaje_ejecucion' => $this->emptyToNull($this->porcentaje_ejecucion),
            'mod_adicion' => $this->mod_adicion,
            'mod_modificacion' => $this->mod_modificacion,
            'mod_suspension' => $this->mod_suspension,
            'mod_prorroga' => $this->mod_prorroga,
            'mod_cesion' => $this->mod_cesion,
            'novedades_contrato' => $this->emptyToNull($this->novedades_contrato),
            'poliza_cumplimiento_numero' => $this->emptyToNull($this->poliza_cumplimiento_numero),
            'poliza_cumplimiento_valor' => $this->emptyToNull($this->poliza_cumplimiento_valor),
            'poliza_cumplimiento_inicio' => $this->emptyToNull($this->poliza_cumplimiento_inicio),
            'poliza_cumplimiento_fin' => $this->emptyToNull($this->poliza_cumplimiento_fin),
            'poliza_rc_numero' => $this->emptyToNull($this->poliza_rc_numero),
            'poliza_rc_valor' => $this->emptyToNull($this->poliza_rc_valor),
            'poliza_rc_inicio' => $this->emptyToNull($this->poliza_rc_inicio),
            'poliza_rc_fin' => $this->emptyToNull($this->poliza_rc_fin),
            'cuenta_bancaria_entidad' => $this->emptyToNull($this->cuenta_bancaria_entidad),
            'numero_cuenta' => $this->emptyToNull($this->numero_cuenta),
            'tipo_cuenta' => $this->tipo_cuenta,
            'regimen_tributario' => $this->regimen_tributario,
            'tipo_facturacion' => $this->tipo_facturacion,
            'cumple_ley_50' => $this->cumple_ley_50,
            'planilla_seguridad_social' => $this->planilla_seguridad_social,
            'certificacion_seguridad_social' => $this->certificacion_seguridad_social,
            'certificacion_obligaciones_laborales' => $this->certificacion_obligaciones_laborales,
            'numero_planilla_ss' => $this->emptyToNull($this->numero_planilla_ss),
            'ibc' => $this->emptyToNull($this->ibc),
            'periodo_salud' => $this->emptyToNull($this->periodo_salud),
            'periodo_pension' => $this->emptyToNull($this->periodo_pension),
            'secop_ii' => $this->secop_ii,
            'siif' => $this->siif,
            'cargar_rit_secop' => $this->cargar_rit_secop,
            'cargar_rut_secop' => $this->cargar_rut_secop,
            'responsable_tramite' => $this->emptyToNull($this->responsable_tramite),
            'cargo_responsable' => $this->emptyToNull($this->cargo_responsable),
            'validacion_gestor' => $this->emptyToNull($this->validacion_gestor),
            'cargo_gestor' => $this->emptyToNull($this->cargo_gestor),
            'vb_directivo' => $this->emptyToNull($this->vb_directivo),
            'cargo_directivo' => $this->emptyToNull($this->cargo_directivo),
        ];
    }

    private function emptyToNull(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }

    private function llenarDocumentosConFacturas(): void
    {
        if (!$this->contrato_id || !$this->numero_pago) {
            return;
        }

        // Consulta directa a BD - no depende de propiedades computadas
        $facturas = \App\Models\Factura::query()
            ->join('detalle_pagos', 'detalle_pagos.factura_id', '=', 'facturas.id')
            ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.contrato_id', $this->contrato_id)
            ->where('pagos.cansecu_tramite', $this->numero_pago)
            ->where('pagos.estado', 'cerrado')
            ->select('facturas.*')
            ->distinct()
            ->get();

        if ($facturas->isEmpty()) {
            return;
        }

        $facturasConMigo = $facturas->where('numero_migo', '!=', null);

        // Primer pago (fecha y valor)
        $pago = \App\Models\Pago::where('contrato_id', $this->contrato_id)
            ->where('cansecu_tramite', $this->numero_pago)
            ->where('estado', 'cerrado')
            ->orderBy('fecha')
            ->first();

        // Index 0: Control de pagos GF-FO-35 → fecha y valor del primer pago
        if (isset($this->documentos_soporte[0])) {
            $this->documentos_soporte[0]['fecha'] = $pago?->fecha?->format('Y-m-d') ?? $this->documentos_soporte[0]['fecha'];
            $this->documentos_soporte[0]['valor'] = $pago?->valor_total ?? $this->documentos_soporte[0]['valor'];
        }

        // Index 1: Factura, cuenta de cobro → números de factura, fecha, valor total, folio
        if (isset($this->documentos_soporte[1])) {
            $numerosFactura = $facturas->map(fn($f) => explode('-', $f->numero)[1] ?? $f->numero)->implode(', ');
            $this->documentos_soporte[1]['nombre_documento'] = 'Factura, cuenta de cobro ' . $numerosFactura;
            $this->documentos_soporte[1]['fecha'] = $facturas->first()?->fecha?->format('Y-m-d') ?? $this->documentos_soporte[1]['fecha'];
            $this->documentos_soporte[1]['valor'] = $facturas->sum(fn($f) => $f->subtotal + $f->total_iva);
            $this->documentos_soporte[1]['folio'] = $facturas->count();
        }

        // Index 2: Acta de entrega → copiar fecha y valor del index 1 (factura)
        if (isset($this->documentos_soporte[2]) && isset($this->documentos_soporte[1])) {
            $this->documentos_soporte[2]['fecha'] = $this->documentos_soporte[1]['fecha'];
            $this->documentos_soporte[2]['valor'] = $this->documentos_soporte[1]['valor'];
        }

        // Index 4: MIGO (MB51) → fecha, valor, números de migo
        if (isset($this->documentos_soporte[4]) && $facturasConMigo->count()) {
            $this->documentos_soporte[4]['fecha'] = $facturasConMigo->first()?->fecha_migo?->format('Y-m-d') ?? $this->documentos_soporte[4]['fecha'];
            $this->documentos_soporte[4]['valor'] = $facturasConMigo->sum(fn($f) => $f->subtotal + $f->total_iva);
            $numerosMigo = $facturasConMigo->pluck('numero_migo')->implode(', ');
            $this->documentos_soporte[4]['nombre_documento'] = 'Reporte/Listado Entradas de Almacén ERP-SAP MB51 ' . $numerosMigo;
        }

        // Index 8 (fila 9): Informe de supervisión → agregar consecutivo del informe
        if (isset($this->documentos_expediente[8]) && $this->contrato) {
            $consecutivo = $this->contrato->cansecu_infor + 1;
            $this->documentos_expediente[8]['nombre_documento'] = 'Informe de supervisión ' . $consecutivo;
        }
    }

    private function saveDocumentos(TramitePago $tramite): void
    {
        $facturasTramite = $this->facturasDelTramite;

        foreach ($this->documentos_soporte as $i => $doc) {
            $fecha = $doc['fecha'] ?? null;
            $valor = $doc['valor'] ?? null;
            $folio = $doc['folio'] ?? null;

            if (empty($fecha) && $i === 0 && $this->fechaPrimerPago) {
                $fecha = $this->fechaPrimerPago;
            }
            if (empty($valor) && $i === 0 && $this->valorPrimerPago) {
                $valor = $this->valorPrimerPago;
            }
            if (empty($valor) && $i === 1 && $facturasTramite->count()) {
                $valor = $facturasTramite->sum(fn($f) => $f->subtotal + $f->total_iva);
            }
            if (empty($fecha) && $i === 1 && $facturasTramite->count()) {
                $fecha = $facturasTramite->first()?->fecha?->format('Y-m-d');
            }
            if (empty($valor) && $i === 4 && $facturasTramite->where('numero_migo', '!=', null)->count()) {
                $valor = $facturasTramite->where('numero_migo', '!=', null)->sum(fn($f) => $f->subtotal + $f->total_iva);
            }
            if (empty($fecha) && $i === 4 && $facturasTramite->where('numero_migo', '!=', null)->count()) {
                $fecha = $facturasTramite->where('numero_migo', '!=', null)->first()?->fecha_migo?->format('Y-m-d');
            }

            $tramite->documentos()->create([
                'tipo' => 'soporte',
                'nombre_documento' => $doc['nombre_documento'] ?? '',
                'fecha' => ! empty($fecha) ? $fecha : null,
                'valor' => ! empty($valor) ? $valor : null,
                'folio' => ! empty($folio) ? $folio : null,
                'reposa_expediente' => false,
            ]);
        }

        foreach ($this->documentos_expediente as $i => $doc) {
            $fecha = $doc['fecha'] ?? null;
            $folio = $doc['folio'] ?? null;

            if (empty($fecha) && $i === 0 && $this->fechaPrimerPago) {
                $fecha = $this->fechaPrimerPago;
            }
            if (empty($folio) && $i === 0 && $facturasTramite->count()) {
                $folio = $facturasTramite->count();
            }
            if (empty($fecha) && $i === 1 && $facturasTramite->count()) {
                $fecha = $facturasTramite->first()?->fecha?->format('Y-m-d');
            }
            if (empty($fecha) && $i === 4 && $facturasTramite->where('numero_migo', '!=', null)->count()) {
                $fecha = $facturasTramite->where('numero_migo', '!=', null)->first()?->fecha_migo?->format('Y-m-d');
            }

            $tramite->documentos()->create([
                'tipo' => 'expediente',
                'nombre_documento' => $doc['nombre_documento'] ?? '',
                'fecha' => ! empty($fecha) ? $fecha : null,
                'valor' => null,
                'folio' => ! empty($folio) ? $folio : null,
                'reposa_expediente' => $doc['reposa_expediente'] ?? false,
            ]);
        }
    }

    private function resetInputFields(): void
    {
        $this->reset();
        $this->numcontrato = '';
        $this->contrato_encontrado = false;
        $this->resultados_busqueda = [];
        $this->documentos_soporte = [];
        $this->documentos_expediente = [];
        $this->modalError = '';
    }
};
?>

<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Trámites de Pago</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Formato GF-FO-36 - Anticipo / Parcial / Total</p>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="w-full sm:max-w-xs">
                    <x-input type="text" wire:model.live="search" placeholder="Buscar por contrato o proveedor..." />
                </div>
                <x-button wire:click="create">
                    <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 16 16">
                        <path d="M15 7H9V1c0-.6-.4-1-1-1S7 .4 7 1v6H1c-.6 0-1 .4-1 1s.4 1 1 1h6v6c0 .6.4 1 1 1s1-.4 1-1V9h6c.6 0 1-.4 1-1s-.4-1-1-1z" />
                    </svg>
                    <span class="ml-2">Nuevo Trámite</span>
                </x-button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th wire:click="sortBy('id')" class="cursor-pointer px-6 py-4 text-left">
                                ID
                                @if ($sortField === 'id')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-4 text-left">Contrato</th>
                            <th class="px-6 py-4 text-left">Proveedor</th>
                            <th class="px-6 py-4 text-left">N° Pago</th>
                            <th class="px-6 py-4 text-left">Valor</th>
                            <th class="px-6 py-4 text-left">Fecha</th>
                            <th class="px-6 py-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->tramitePagos as $tramite)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">{{ $tramite->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">
                                    {{ $tramite->contrato->numcontrato ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $tramite->contrato->proveedor->nombre ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $tramite->numero_pago }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${{ number_format($tramite->valor_pago_solicitado, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $tramite->fecha_tramite?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('tramite-pagos.download-plantilla', $tramite->id) }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition" title="Descargar Word (Plantilla)">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Plantilla
                                        </a>
                                        <button wire:click="edit({{ $tramite->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 transition" title="Editar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        <button wire:click="confirmDelete({{ $tramite->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition" title="Eliminar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No se encontraron trámites de pago.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->tramitePagos->links() }}
            </div>
        </div>

        {{-- Modal Formulario --}}
        <div wire:ignore.self wire:key="form-modal"
            x-data="{ show: @entangle('showFormModal') }"
            x-show="show"
            @keydown.escape.window="show = false"
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500 opacity-75 dark:bg-gray-900"
                x-show="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            </div>
            <div x-show="show"
                class="mb-6 w-full max-w-5xl mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="px-6 py-4 max-h-[80vh] overflow-y-auto">
                    @if ($modalError)
                        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            {{ $modalError }}
                        </div>
                    @endif
                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editing ? 'Editar Trámite de Pago' : 'Crear Trámite de Pago' }}
                    </div>

                    {{-- Selección de Contrato --}}
                    <div class="mb-6" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contrato <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" wire:model.live="numcontrato" 
                                wire:keydown.debounce.300ms="buscarContrato"
                                @focus="open = true"
                                @keydown.escape="open = false"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Digite el número del contrato..."
                                autocomplete="off" />
                            
                            @if ($contrato_encontrado)
                                <div class="mt-1 flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Contrato encontrado
                                </div>
                            @endif

                            @if (count($resultados_busqueda) > 0 && !$contrato_encontrado)
                                <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                    @foreach ($resultados_busqueda as $resultado)
                                        <button type="button" 
                                            wire:click="seleccionarContrato({{ $resultado['id'] }})"
                                            @click="open = false"
                                            class="w-full px-4 py-2 text-left hover:bg-violet-50 dark:hover:bg-violet-900/20 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $resultado['numcontrato'] }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $resultado['proveedor']['nombre'] ?? '-' }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <input type="hidden" wire:model="contrato_id" />
                        <x-input-error for="contrato_id" />
                        <x-input-error for="numcontrato" />
                    </div>

                    <div class="space-y-6">
                        {{-- Sección: Datos del Pago --}}
                        <div class="border-l-4 border-violet-500 bg-violet-50/50 dark:bg-violet-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-400 mb-3">Datos del Pago</h3>

                            @if ($this->pagoEstado && $this->pagoEstado !== 'cerrado')
                                <div class="mb-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700">
                                    <p class="text-sm text-amber-700 dark:text-amber-400 font-semibold">
                                        ⚠️ El pago N° {{ str_pad($this->numero_pago, 3, '0', STR_PAD_LEFT) }} está en estado "<span class="uppercase">{{ $this->pagoEstado }}</span>". Debe confirmar el pago antes de crear su trámite.
                                    </p>
                                </div>
                            @elseif (!$this->pagoEstado && $this->contrato_id && $this->numero_pago)
                                <div class="mb-3 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700">
                                    <p class="text-sm text-red-700 dark:text-red-400 font-semibold">
                                        ❌ No existe pago para el trámite N° {{ str_pad($this->numero_pago, 3, '0', STR_PAD_LEFT) }}.
                                    </p>
                                </div>
                            @endif
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                <div>
                                    <x-label for="fecha_tramite" value="Fecha Trámite *" />
                                    <x-input id="fecha_tramite" type="date" wire:model="fecha_tramite" class="w-full" />
                                    <x-input-error for="fecha_tramite" />
                                </div>
                                <div>
                                    <x-label for="numero_pago" value="N° Pago *" />
                                    <x-input id="numero_pago" type="number" wire:model="numero_pago" class="w-full" min="1" />
                                    <x-input-error for="numero_pago" />
                                </div>
                                <div>
                                    <x-label for="valor_pago_solicitado" value="Valor Pago Solicitado *" />
                                    @if ($this->totalFacturasTramite > 0)
                                        <span class="block w-full text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-violet-50 dark:bg-violet-900/20 py-2 px-3 text-violet-700 dark:text-violet-300 font-semibold">${{ number_format($this->totalFacturasTramite, 2, ',', '.') }}</span>
                                    @else
                                        <x-input id="valor_pago_solicitado" type="number" step="0.01" wire:model="valor_pago_solicitado" class="w-full" />
                                    @endif
                                    <x-input-error for="valor_pago_solicitado" />
                                </div>
                                <div>
                                    <x-label value="Consecutivo Informe" />
                                    <span class="block w-full form-input bg-gray-50 dark:bg-gray-700 text-violet-600 dark:text-violet-400 font-bold">{{ $siguiente_informe }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Datos del Contrato --}}
                        <div class="border-l-4 border-blue-500 bg-blue-50/50 dark:bg-blue-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-400 mb-3">Datos del Contrato</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-label for="registro_presupuestal" value="Registro Presupuestal" />
                                    <x-input id="registro_presupuestal" type="text" wire:model="registro_presupuestal" class="w-full" />
                                </div>
                                <div class="flex items-center gap-2 pt-6">
                                    <input type="checkbox" wire:model="vigencia_actual" id="vigencia_actual" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label for="vigencia_actual" class="text-sm text-gray-700 dark:text-gray-300">Vigencia Actual</label>
                                </div>
                                <div>
                                    <x-label for="valor_inicial_contrato" value="Valor Inicial Contrato" />
                                    <span class="block w-full text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2 px-3 text-gray-800 dark:text-gray-200 font-semibold">${{ number_format($valor_inicial_contrato, 2, ',', '.') }}</span>
                                </div>
                                <div>
                                    <x-label for="valor_adiciones" value="Valor Adiciones" />
                                    <span class="block w-full text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2 px-3 text-gray-800 dark:text-gray-200 font-semibold">${{ number_format($valor_adiciones, 2, ',', '.') }}</span>
                                </div>
                                <div>
                                    <x-label for="valor_reducciones" value="Valor Reducciones" />
                                    <span class="block w-full text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2 px-3 text-gray-800 dark:text-gray-200 font-semibold">${{ number_format($valor_reducciones, 2, ',', '.') }}</span>
                                </div>
                                <div>
                                    <x-label for="valor_total_contrato" value="Valor Total Contrato" />
                                    <span class="block w-full text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2 px-3 text-gray-800 dark:text-gray-200 font-semibold">${{ number_format($valor_total_contrato, 2, ',', '.') }}</span>
                                </div>
                                <div>
                                    <x-label for="contrato_interadministrativo" value="Contrato Interadministrativo" />
                                    <x-input id="contrato_interadministrativo" type="text" wire:model="contrato_interadministrativo" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="fecha_legalizacion" value="Fecha Legalización" />
                                    <x-input id="fecha_legalizacion" type="date" wire:model="fecha_legalizacion" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="fecha_finalizacion" value="Fecha Finalización" />
                                    <x-input id="fecha_finalizacion" type="date" wire:model="fecha_finalizacion" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="porcentaje_ejecucion" value="% Ejecución" />
                                    <x-input id="porcentaje_ejecucion" type="number" step="0.01" min="0" max="100" wire:model="porcentaje_ejecucion" class="w-full" />
                                </div>
                            </div>
                            {{-- Modificaciones --}}
                            <div class="mt-4">
                                <x-label value="Modificaciones del Contrato" />
                                <div class="flex flex-wrap gap-4 mt-2">
                                    @foreach(['mod_adicion' => 'Adición', 'mod_modificacion' => 'Modificación', 'mod_suspension' => 'Suspensión', 'mod_prorroga' => 'Prórroga', 'mod_cesion' => 'Cesión'] as $key => $label)
                                        <label class="flex items-center gap-1 text-sm">
                                            <input type="checkbox" wire:model="{{ $key }}" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-4">
                                <x-label for="novedades_contrato" value="Novedades del Contrato" />
                                <textarea wire:model="novedades_contrato" class="w-full form-textarea rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" rows="2"></textarea>
                            </div>
                        </div>

                        {{-- Sección: Garantías --}}
                        <div class="border-l-4 border-yellow-500 bg-yellow-50/50 dark:bg-yellow-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-yellow-700 dark:text-yellow-400 mb-3">Garantías - Pólizas</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2 text-xs font-semibold text-gray-500 uppercase">Póliza de Cumplimiento</div>
                                <div>
                                    <x-label for="poliza_cumplimiento_numero" value="N° Póliza" />
                                    <x-input id="poliza_cumplimiento_numero" type="text" wire:model="poliza_cumplimiento_numero" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_cumplimiento_valor" value="Valor Asegurado" />
                                    <x-input id="poliza_cumplimiento_valor" type="number" step="0.01" wire:model="poliza_cumplimiento_valor" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_cumplimiento_inicio" value="Inicio Vigencia" />
                                    <x-input id="poliza_cumplimiento_inicio" type="date" wire:model="poliza_cumplimiento_inicio" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_cumplimiento_fin" value="Fin Vigencia" />
                                    <x-input id="poliza_cumplimiento_fin" type="date" wire:model="poliza_cumplimiento_fin" class="w-full" />
                                </div>
                                <div class="sm:col-span-2 text-xs font-semibold text-gray-500 uppercase mt-2">Póliza Responsabilidad Civil</div>
                                <div>
                                    <x-label for="poliza_rc_numero" value="N° Póliza" />
                                    <x-input id="poliza_rc_numero" type="text" wire:model="poliza_rc_numero" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_rc_valor" value="Valor Asegurado" />
                                    <x-input id="poliza_rc_valor" type="number" step="0.01" wire:model="poliza_rc_valor" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_rc_inicio" value="Inicio Vigencia" />
                                    <x-input id="poliza_rc_inicio" type="date" wire:model="poliza_rc_inicio" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="poliza_rc_fin" value="Fin Vigencia" />
                                    <x-input id="poliza_rc_fin" type="date" wire:model="poliza_rc_fin" class="w-full" />
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Datos Financieros --}}
                        <div class="border-l-4 border-green-500 bg-green-50/50 dark:bg-green-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-green-700 dark:text-green-400 mb-3">Datos Financieros del Contratista</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-label for="cuenta_bancaria_entidad" value="Entidad Bancaria" />
                                    <x-input id="cuenta_bancaria_entidad" type="text" wire:model="cuenta_bancaria_entidad" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="numero_cuenta" value="N° Cuenta" />
                                    <x-input id="numero_cuenta" type="text" wire:model="numero_cuenta" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="tipo_cuenta" value="Tipo Cuenta" />
                                    <select wire:model="tipo_cuenta" class="w-full form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <option value="ahorro">Ahorro</option>
                                        <option value="corriente">Corriente</option>
                                    </select>
                                </div>
                                <div>
                                    <x-label for="regimen_tributario" value="Régimen Tributario" />
                                    <select wire:model="regimen_tributario" class="w-full form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <option value="iva">Responsable de IVA</option>
                                        <option value="no_iva">No Responsable de IVA</option>
                                        <option value="simple">Régimen Simple</option>
                                    </select>
                                </div>
                                <div>
                                    <x-label for="tipo_facturacion" value="Tipo Facturación" />
                                    <select wire:model="tipo_facturacion" class="w-full form-select rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <option value="electronica">Electrónica</option>
                                        <option value="cuenta_cobro">Cuenta de Cobro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Cumplimiento --}}
                        <div class="border-l-4 border-emerald-500 bg-emerald-50/50 dark:bg-emerald-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-3">Cumplimiento Ley 50/1990 - Seguridad Social</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="cumple_ley_50" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Cumple Ley 50/1990</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="planilla_seguridad_social" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Planilla SS Pagada</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="certificacion_seguridad_social" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Certificación Pago SS</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="certificacion_obligaciones_laborales" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Certif. Oblig. Laborales</label>
                                </div>
                                <div>
                                    <x-label for="numero_planilla_ss" value="N° Planilla SS" />
                                    <x-input id="numero_planilla_ss" type="text" wire:model="numero_planilla_ss" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="ibc" value="IBC" />
                                    <x-input id="ibc" type="text" wire:model="ibc" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="periodo_salud" value="Periodo Salud" />
                                    <x-input id="periodo_salud" type="text" wire:model="periodo_salud" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="periodo_pension" value="Periodo Pensión" />
                                    <x-input id="periodo_pension" type="text" wire:model="periodo_pension" class="w-full" />
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Aprobación --}}
                        <div class="border-l-4 border-purple-500 bg-purple-50/50 dark:bg-purple-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-purple-700 dark:text-purple-400 mb-3">Aprobación de Facturas</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="secop_ii" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">SECOP II</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="siif" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">SIIF (cuando sea Electrónica)</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="cargar_rit_secop" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Cargar RIT en SECOP II</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="cargar_rut_secop" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    <label class="text-sm text-gray-700 dark:text-gray-300">Cargar RUT en SECOP II</label>
                                </div>
                            </div>
                        </div>

                        {{-- Sección: Documentos Soporte --}}
                        <div class="border-l-4 border-orange-500 bg-orange-50/50 dark:bg-orange-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-orange-700 dark:text-orange-400 mb-3">Documentos Soporte</h3>
                            @if (count($documentos_soporte) > 0)
                                <div class="overflow-x-auto mb-3">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                                <th class="px-2 py-1 text-left w-8">#</th>
                                                <th class="px-2 py-1 text-left">Documento</th>
                                                <th class="px-2 py-1 text-left w-28">Fecha</th>
                                                <th class="px-2 py-1 text-left w-24">Valor</th>
                                                <th class="px-2 py-1 text-left w-16">Folio</th>
                                                <th class="px-2 py-1 text-center w-10">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($documentos_soporte as $i => $doc)
                                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                                    <td class="px-2 py-1">{{ $i + 1 }}</td>
                                                    <td class="px-1 py-0.5">
                                                        @if (str_contains(strtolower($doc['nombre_documento'] ?? ''), 'factura') && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $doc['nombre_documento'] }} {{ $this->facturasDelTramite->map(fn($f) => explode('-', $f->numero)[1] ?? $f->numero)->implode(', ') }}</span>
                                                        @elseif ($i === 4 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'mb51') && $this->facturasDelTramite->where('numero_migo', '!=', null)->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $doc['nombre_documento'] }} {{ $this->facturasDelTramite->where('numero_migo', '!=', null)->pluck('numero_migo')->implode(', ') }}</span>
                                                        @else
                                                            <input type="text" wire:model.live="documentos_soporte.{{ $i }}.nombre_documento"
                                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                        @endif
                                                    </td>
                                                    <td class="px-1 py-0.5">
                                                        @if ($i === 0 && $this->fechaPrimerPago)
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">{{ \Carbon\Carbon::parse($this->fechaPrimerPago)->format('d/m/Y') }}</span>
                                                        @elseif (str_contains(strtolower($doc['nombre_documento'] ?? ''), 'factura') && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $this->facturasDelTramite->map(fn($f) => $f->fecha?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @elseif ($i === 2 && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">{{ $this->facturasDelTramite->map(fn($f) => $f->fecha?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @elseif ($i === 4 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'mb51') && $this->facturasDelTramite->where('numero_migo', '!=', null)->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $this->facturasDelTramite->where('numero_migo', '!=', null)->map(fn($f) => $f->fecha_migo?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @else
                                                            <input type="date" wire:model.live="documentos_soporte.{{ $i }}.fecha"
                                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                        @endif
                                                    </td>
                                                    <td class="px-1 py-0.5">
                                                        @if ($i === 0 && $this->valorPrimerPago)
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">${{ number_format($this->valorPrimerPago, 2, ',', '.') }}</span>
                                                        @elseif ($i === 1 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'factura') && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">${{ number_format($this->facturasDelTramite->sum(fn($f) => $f->subtotal + $f->total_iva), 2, ',', '.') }}</span>
                                                        @elseif ($i === 2 && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">${{ number_format($this->facturasDelTramite->sum(fn($f) => $f->subtotal + $f->total_iva), 2, ',', '.') }}</span>
                                                        @elseif ($i === 4 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'mb51') && $this->facturasDelTramite->where('numero_migo', '!=', null)->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">${{ number_format($this->facturasDelTramite->where('numero_migo', '!=', null)->sum(fn($f) => $f->subtotal + $f->total_iva), 2, ',', '.') }}</span>
                                                        @else
                                                            <input type="number" step="0.01" wire:model.live="documentos_soporte.{{ $i }}.valor"
                                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" placeholder="0" />
                                                        @endif
                                                    </td>
                                                    <td class="px-1 py-0.5">
                                                        <input type="number" wire:model.live="documentos_soporte.{{ $i }}.folio"
                                                            class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                    </td>
                                                    <td class="px-2 py-1 text-center">
                                                        <button wire:click="removeDocumentoSoporte({{ $i }})" class="text-red-500 hover:text-red-700">✕</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <div class="grid grid-cols-5 gap-2 items-end">
                                <div class="col-span-2">
                                    <x-input type="text" wire:model="new_doc_soporte_nombre" placeholder="Nombre documento" class="w-full text-xs" />
                                </div>
                                <div>
                                    <x-input type="date" wire:model="new_doc_soporte_fecha" class="w-full text-xs" />
                                </div>
                                <div>
                                    <x-input type="number" step="0.01" wire:model="new_doc_soporte_valor" placeholder="Valor" class="w-full text-xs" />
                                </div>
                                <div class="flex gap-1 items-center">
                                    <x-input type="number" wire:model="new_doc_soporte_folio" placeholder="Folio" class="w-full text-xs" />
                                    <x-button wire:click="addDocumentoSoporte" class="text-xs px-2 py-1">+</x-button>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" wire:model="guardar_como_plantilla_soporte" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    Guardar como plantilla para futuros trámites
                                </label>
                            </div>
                        </div>

                        {{-- Sección: Documentos Expediente --}}
                        <div class="border-l-4 border-red-500 bg-red-50/50 dark:bg-red-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3">Documentos en Expediente del Contrato</h3>
                            @if (count($documentos_expediente) > 0)
                                <div class="overflow-x-auto mb-3">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                                <th class="px-2 py-1 text-left w-8">#</th>
                                                <th class="px-2 py-1 text-left">Documento</th>
                                                <th class="px-2 py-1 text-center w-14">Reposa</th>
                                                <th class="px-2 py-1 text-left w-28">Fecha</th>
                                                <th class="px-2 py-1 text-left w-16">Folio</th>
                                                <th class="px-2 py-1 text-center w-10">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($documentos_expediente as $i => $doc)
                                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                                    <td class="px-2 py-1">{{ $i + 1 }}</td>
                                                    <td class="px-1 py-0.5">
                                                        @if (str_contains(strtolower($doc['nombre_documento'] ?? ''), 'factura') && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $doc['nombre_documento'] }} {{ $this->facturasDelTramite->map(fn($f) => explode('-', $f->numero)[1] ?? $f->numero)->implode(', ') }}</span>
                                                        @elseif ($i === 4 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'almacén') && $this->facturasDelTramite->where('numero_migo', '!=', null)->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $doc['nombre_documento'] }} {{ $this->facturasDelTramite->where('numero_migo', '!=', null)->pluck('numero_migo')->implode(', ') }}</span>
                                                        @elseif ($i === 8)
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">{{ $doc['nombre_documento'] }} {{ $this->contrato ? ($this->contrato->cansecu_infor + 1) : '' }}</span>
                                                        @else
                                                            <input type="text" wire:model.live="documentos_expediente.{{ $i }}.nombre_documento"
                                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                        @endif
                                                    </td>
                                                    <td class="px-1 py-0.5 text-center">
                                                        <input type="checkbox" wire:model.live="documentos_expediente.{{ $i }}.reposa_expediente"
                                                            class="rounded border-gray-300 text-violet-500 focus:ring-violet-500" />
                                                    </td>
                                                    <td class="px-1 py-0.5">
                                                        @if ($i === 0 && $this->fechaPrimerPago)
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">{{ \Carbon\Carbon::parse($this->fechaPrimerPago)->format('d/m/Y') }}</span>
                                                        @elseif (str_contains(strtolower($doc['nombre_documento'] ?? ''), 'factura') && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $this->facturasDelTramite->map(fn($f) => $f->fecha?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @elseif ($i === 2 && $this->facturasDelTramite->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2">{{ $this->facturasDelTramite->map(fn($f) => $f->fecha?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @elseif ($i === 4 && str_contains(strtolower($doc['nombre_documento'] ?? ''), 'almacén') && $this->facturasDelTramite->where('numero_migo', '!=', null)->count())
                                                            <span class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 whitespace-pre-wrap break-words">{{ $this->facturasDelTramite->where('numero_migo', '!=', null)->map(fn($f) => $f->fecha_migo?->format('d/m/Y'))->unique()->implode(', ') }}</span>
                                                        @else
                                                            <input type="date" wire:model.live="documentos_expediente.{{ $i }}.fecha"
                                                                class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                        @endif
                                                    </td>
                                                    <td class="px-1 py-0.5">
                                                        <input type="number" wire:model.live="documentos_expediente.{{ $i }}.folio"
                                                            class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500 py-1" />
                                                    </td>
                                                    <td class="px-2 py-1 text-center">
                                                        <button wire:click="removeDocumentoExpediente({{ $i }})" class="text-red-500 hover:text-red-700">✕</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <div class="grid grid-cols-5 gap-2 items-end">
                                <div class="col-span-2">
                                    <x-input type="text" wire:model="new_doc_exp_nombre" placeholder="Nombre documento" class="w-full text-xs" />
                                </div>
                                <div>
                                    <x-input type="date" wire:model="new_doc_exp_fecha" class="w-full text-xs" />
                                </div>
                                <div class="flex gap-2 items-center">
                                    <x-input type="number" wire:model="new_doc_exp_folio" placeholder="Folio" class="w-full text-xs" />
                                    <label class="flex items-center gap-1 text-xs">
                                        <input type="checkbox" wire:model="new_doc_exp_reposa" class="rounded border-gray-300 text-violet-500">
                                        Reposa
                                    </label>
                                </div>
                                <div>
                                    <x-button wire:click="addDocumentoExpediente" class="text-xs px-2 py-1">+</x-button>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" wire:model="guardar_como_plantilla_exp" class="rounded border-gray-300 text-violet-500 focus:ring-violet-500">
                                    Guardar como plantilla para futuros trámites
                                </label>
                            </div>
                        </div>

                        {{-- Sección: Firmas --}}
                        <div class="border-l-4 border-gray-500 bg-gray-50/50 dark:bg-gray-900/10 rounded-r-lg p-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-400 mb-3">Firmas y Validaciones</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-label for="responsable_tramite" value="Responsable Trámite" />
                                    <x-input id="responsable_tramite" type="text" wire:model="responsable_tramite" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="cargo_responsable" value="Cargo Responsable" />
                                    <x-input id="cargo_responsable" type="text" wire:model="cargo_responsable" class="w-full" />
                                </div>
                                <div></div>
                                <div>
                                    <x-label for="validacion_gestor" value="Gestor Contractual" />
                                    <x-input id="validacion_gestor" type="text" wire:model="validacion_gestor" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="cargo_gestor" value="Cargo Gestor" />
                                    <x-input id="cargo_gestor" type="text" wire:model="cargo_gestor" class="w-full" />
                                </div>
                                <div></div>
                                <div>
                                    <x-label for="vb_directivo" value="Directivo" />
                                    <x-input id="vb_directivo" type="text" wire:model="vb_directivo" class="w-full" />
                                </div>
                                <div>
                                    <x-label for="cargo_directivo" value="Cargo Directivo" />
                                    <x-input id="cargo_directivo" type="text" wire:model="cargo_directivo" class="w-full" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-row justify-between px-6 py-4 bg-gray-100 dark:bg-gray-900/20 text-right gap-2">
                    <div>
                        @if ($editing && $tramite_pago_id)
                            <a href="{{ route('tramite-pagos.download-plantilla', $tramite_pago_id) }}" target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Descargar Plantilla
                            </a>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <x-secondary-button wire:click="closeModal">
                            Cancelar
                        </x-secondary-button>
                        <x-button wire:click="{{ $editing ? 'update' : 'store' }}">
                            {{ $editing ? 'Actualizar' : 'Guardar' }}
                        </x-button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Eliminar --}}
        <div wire:ignore.self wire:key="delete-modal"
            x-data="{ show: @entangle('showDeleteModal') }"
            x-show="show"
            @keydown.escape.window="show = false"
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500 opacity-75 dark:bg-gray-900"
                x-show="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            </div>
            <div x-show="show"
                class="mb-6 w-full max-w-lg mx-auto bg-white rounded-lg overflow-hidden shadow-xl transition-all dark:bg-gray-800 relative"
                x-trap.inert.noscroll="show"
                x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="px-6 py-4">
                    <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Eliminar Trámite de Pago
                    </div>
                    <div class="mt-4 text-gray-600 dark:text-gray-400">
                        ¿Está seguro de que desea eliminar este trámite de pago? Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-900/20 text-right">
                    <x-secondary-button wire:click="closeModal" class="mr-2">
                        Cancelar
                    </x-secondary-button>
                    <x-danger-button wire:click="delete">
                        Eliminar
                    </x-danger-button>
                </div>
            </div>
        </div>
    </div>
</div>
