<?php

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Municipio;
use App\Models\Itemcontrato;
use App\Services\CalculadoraRetenciones;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // Paso 1: Buscar contrato
    public string $numcontrato = '';
    public ?Contrato $contrato = null;
    public string $contratoError = '';

    // Paso 2: Datos de la factura
    public ?int $factura_id = null;
    public string $numero_factura = '';
    public string $fecha_factura = '';
    public string $numero_migo = '';
    public string $fecha_migo = '';
    public ?int $municipio_default_id = null;
    public string $estampilla_default_id = '';
    public ?int $dependencia_id = null;

    // Paso 3: Líneas
    public array $lineas = [];
    public array $retencionesPorLinea = [];
    public array $pendientesPorLinea = [];

    // Modo ajuste
    public bool $esAjuste = false;
    public float $valorAjuste = 0;
    public float $porcentajeIvaAjuste = 19;

    // Paso 4: Guardado
    public bool $guardando = false;

    // Modo edición
    public bool $editando = false;
    public string $estadoFactura = 'borrador';

    // Buscador
    #[Url]
    public string $search = '';

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->cargarFactura($id);
        }
    }

    public function cargarFactura(int $id): void
    {
        $factura = Factura::with([
            'contrato.proveedor',
            'contrato.movirubros.rubro',
            'contrato.itemcontratos.producto',
            'lineas.itemcontrato.producto',
            'lineas.retenciones.retencion',
        ])->find($id);

        if (!$factura) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Factura no encontrada.');
            return;
        }

        if ($factura->estado !== 'borrador') {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Solo se pueden editar facturas en estado borrador.');
            return;
        }

        $this->editando = true;
        $this->factura_id = $factura->id;
        $this->contrato = $factura->contrato;
        $this->numcontrato = $factura->contrato->numcontrato;
        $this->estadoFactura = $factura->estado;

        $partes = explode('-', $factura->numero);
        $this->numero_factura = $partes[1] ?? $factura->numero;
        $this->fecha_factura = $factura->fecha->format('Y-m-d');
        $this->numero_migo = $factura->numero_migo ?? '';
        $this->fecha_migo = $factura->fecha_migo ? $factura->fecha_migo->format('Y-m-d') : '';
        $this->municipio_default_id = $factura->municipio_id;
        $this->dependencia_id = $factura->dependencia_id;

        $primeraLinea = $factura->lineas->first();
        $this->estampilla_default_id = $primeraLinea && $primeraLinea->estampilla_retencion_id
            ? (string) $primeraLinea->estampilla_retencion_id
            : '';

        $this->lineas = [];
        $this->retencionesPorLinea = [];
        $this->pendientesPorLinea = [];

        foreach ($factura->lineas as $fl) {
            $idx = count($this->lineas);
            $item = $fl->itemcontrato;
            $esAjuste = $fl->es_ajuste ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores guardados
                $this->lineas[$idx] = [
                    'factura_linea_id' => $fl->id,
                    'itemcontrato_id' => $fl->itemcontrato_id,
                    'producto_nombre' => $item->producto->name ?? '—',
                    'valor_costo_unit' => $fl->valor_base,
                    'iva_unit' => $fl->porcentaje_iva ?? 0,
                    'valor_iva_unit' => $fl->valor_iva,
                    'valor_con_iva_unit' => $fl->valor_con_iva,
                    'cantidad' => $fl->cantidad,
                    'tipo_adquisicion' => $fl->tipo_adquisicion ?? 'bien',
                    'municipio_id' => $fl->municipio_id,
                    'estampilla_retencion_id' => $fl->estampilla_retencion_id,
                    'valor_base' => $fl->valor_base,
                    'valor_iva' => $fl->valor_iva,
                    'valor_con_iva' => $fl->valor_con_iva,
                    'es_ajuste' => true,
                    'porcentaje_iva' => $fl->porcentaje_iva,
                ];
            } else {
                // Línea normal: valores del itemcontrato
                $this->lineas[$idx] = [
                    'factura_linea_id' => $fl->id,
                    'itemcontrato_id' => $fl->itemcontrato_id,
                    'producto_nombre' => $item->producto->name ?? '—',
                    'valor_costo_unit' => $item->valor_costo,
                    'iva_unit' => $item->iva,
                    'valor_iva_unit' => $item->valor_iva,
                    'valor_con_iva_unit' => $item->valor_con_iva,
                    'cantidad' => $fl->cantidad,
                    'tipo_adquisicion' => $fl->tipo_adquisicion ?? 'bien',
                    'municipio_id' => $fl->municipio_id,
                    'estampilla_retencion_id' => $fl->estampilla_retencion_id,
                    'valor_base' => $fl->valor_base,
                    'valor_iva' => $fl->valor_iva,
                    'valor_con_iva' => $fl->valor_con_iva,
                ];
            }

            $this->retencionesPorLinea[$idx] = $fl->retenciones->map(fn($r) => [
                'retencion' => $r->retencion,
                'porcentaje' => $r->porcentaje_aplicado,
                'valor_retenido' => $r->valor_retenido,
            ])->toArray();

            $this->pendientesPorLinea[$idx] = [];
        }
    }

    // Municipios para selects
    #[Computed]
    public function municipios()
    {
        return Municipio::orderBy('nombre')->get();
    }

    // Estampillas disponibles (retenciones territoriales)
    #[Computed]
    public function estampillas()
    {
        return \App\Models\Retencion::where('tipo', 'territorial')
            ->whereHas('estampillaTarifas')
            ->orderBy('name')
            ->get();
    }

    // Dependencias filtradas por regional del usuario
    #[Computed]
    public function dependencias()
    {
        $user = Auth::user();
        $regionalId = $user->regional_id ?? null;

        if (!$regionalId) {
            return \App\Models\Dependencia::with(['municipio', 'regional'])->orderBy('name')->get();
        }

        return \App\Models\Dependencia::with(['municipio', 'regional'])
            ->where('regional_id', $regionalId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Saldo disponible del contrato.
     */
    #[Computed]
    public function saldoDisponible()
    {
        if (!$this->contrato) return 0;
        return $this->contrato->saldo;
    }

    /**
     * Total de la factura (suma de líneas - retenciones).
     */
    #[Computed]
    public function totalFactura()
    {
        $subtotal = 0;
        $totalIva = 0;
        $totalRetenciones = 0;

        foreach ($this->lineas as $idx => $linea) {
            $subtotal += $linea['valor_base'] ?? 0;
            $totalIva += $linea['valor_iva'] ?? 0;
            $totalRetenciones += collect($this->retencionesPorLinea[$idx] ?? [])->sum('valor_retenido');
        }

        return [
            'subtotal' => $subtotal,
            'total_iva' => $totalIva,
            'total_retenciones' => $totalRetenciones,
            'total' => $subtotal + $totalIva - $totalRetenciones,
        ];
    }

    #[Computed]
    public function itemcontratosDisponibles()
    {
        if (!$this->contrato) return collect();
        $idsYaEnFactura = collect($this->lineas)->pluck('itemcontrato_id')->toArray();
        return $this->contrato->itemcontratos()
            ->with('producto')
            ->whereNotIn('id', $idsYaEnFactura)
            ->get();
    }

    // ------------------------------------------------------------------
    // PASO 1: Buscar contrato
    // ------------------------------------------------------------------

    public function buscarContrato(): void
    {
        $this->contratoError = '';
        $this->contrato = null;
        $this->lineas = [];
        $this->retencionesPorLinea = [];
        $this->factura_id = null;
        $this->esAjuste = false;
        $this->valorAjuste = 0;
        $this->porcentajeIvaAjuste = 19;

        $numero = trim($this->numcontrato);
        if ($numero === '') {
            $this->contratoError = 'Ingrese el número del contrato.';
            return;
        }

        $contrato = Contrato::with(['proveedor', 'movirubros', 'itemcontratos.producto', 'itemcontratos.movirubro', 'itemcontratos.rubro'])
            ->where('numcontrato', $numero)
            ->first();

        if (!$contrato) {
            $this->contratoError = 'No se encontró un contrato con el número ' . $numero . '.';
            return;
        }

        $this->contrato = $contrato;
        $this->fecha_factura = now()->format('Y-m-d');
        $this->numero_factura = '';

        // Municipio por defecto: el de la regional del usuario
        $user = Auth::user();
        $this->municipio_default_id = $user->regional?->municipio_id ?? null;
    }

    // ------------------------------------------------------------------
    // PASO 2: Crear factura y agregar líneas
    // ------------------------------------------------------------------

    public function crearFactura(): void
    {
        if (!$this->contrato) return;
        if ($this->guardando) return;

        $this->guardando = true;

        if (empty(trim($this->numero_factura))) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe ingresar el número de la factura.');
            $this->guardando = false;
            return;
        }

        if (empty($this->fecha_factura)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar la fecha de la factura.');
            $this->guardando = false;
            return;
        }

        if (empty($this->dependencia_id)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar una Dependencia / Comedor.');
            $this->guardando = false;
            return;
        }

        if (empty($this->lineas)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe agregar al menos una línea de producto a la factura.');
            $this->guardando = false;
            return;
        }

        // Generar código interno: proveedor_id-numero-año
        $year = date('Y', strtotime($this->fecha_factura));
        $numeroInterno = $this->contrato->proveedor_id . '-' . trim($this->numero_factura) . '-' . $year;

        // Verificar que no exista
        $existe = Factura::where('numero', $numeroInterno)->exists();
        if ($existe) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Ya existe una factura con el número ' . $this->numero_factura . ' para este proveedor en el año ' . $year . '.');
            $this->guardando = false;
            return;
        }

        // Validar que servicios tengan municipio
        foreach ($this->lineas as $idx => $linea) {
            if (($linea['tipo_adquisicion'] ?? 'bien') === 'servicio' && empty($linea['municipio_id'])) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'La línea "' . ($linea['producto_nombre'] ?? '') . '" es un servicio y debe tener un municipio seleccionado para Reteica.');
                $this->guardando = false;
                return;
            }
        }

        // Validar saldo de rubros: sumar valor_con_iva de las líneas agrupadas por itemcontrato
        $porItemcontrato = [];
        foreach ($this->lineas as $linea) {
            $itemcontratoId = $linea['itemcontrato_id'];
            $porItemcontrato[$itemcontratoId] = ($porItemcontrato[$itemcontratoId] ?? 0) + ($linea['valor_con_iva'] ?? 0);
        }

        foreach ($porItemcontrato as $itemcontratoId => $totalLineas) {
            $itemcontrato = Itemcontrato::with('movirubro')->find($itemcontratoId);
            if (!$itemcontrato || !$itemcontrato->movirubro) continue;

            $movirubro = $itemcontrato->movirubro;
            $saldoDisponible = (float) $movirubro->saldo_rubro;

            $facturasExistentes = \App\Models\FacturaLinea::whereHas('factura', function ($q) use ($movirubro) {
                $q->where('contrato_id', $this->contrato->id)
                  ->whereIn('estado', ['borrador', 'emitida']);
            })->whereHas('itemcontrato', function ($q) use ($movirubro) {
                $q->where('movirubro_id', $movirubro->id);
            })->sum('valor_con_iva');

            $saldoRestante = $saldoDisponible - $facturasExistentes;

            if ($totalLineas > $saldoRestante + 0.01) {
                $nombreRubro = $movirubro->rubro->nombre_rubro ?? 'Sin nombre';
                $codigoRubro = $movirubro->rubro->codigo_rubro ?? '';
                $this->dispatch('alerta', tipo: 'error', mensaje: 'El rubro "' . $codigoRubro . ' - ' . $nombreRubro . '" no tiene saldo suficiente. Saldo disponible: $' . number_format($saldoRestante, 2, ',', '.') . ', total de la factura para este rubro: $' . number_format($totalLineas, 2, ',', '.'));
                $this->guardando = false;
                return;
            }
        }

        $servicio = new CalculadoraRetenciones();

        $factura = Factura::create([
            'proveedor_id' => $this->contrato->proveedor_id,
            'contrato_id' => $this->contrato->id,
            'numero' => $numeroInterno,
            'numero_migo' => $this->numero_migo ?: null,
            'fecha_migo' => $this->fecha_migo ?: null,
            'fecha' => $this->fecha_factura,
            'municipio_id' => $this->municipio_default_id,
            'dependencia_id' => $this->dependencia_id,
            'estado' => 'borrador',
        ]);

        $this->factura_id = $factura->id;

        // Crear líneas desde los itemcontratos seleccionados
        foreach ($this->lineas as $idx => $linea) {
            $itemcontrato = Itemcontrato::find($linea['itemcontrato_id']);
            if (!$itemcontrato) continue;

            $cantidad = max(1, (float) ($linea['cantidad'] ?? 1));
            $esAjuste = $linea['es_ajuste'] ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores personalizados
                $facturaLinea = FacturaLinea::create([
                    'factura_id' => $factura->id,
                    'itemcontrato_id' => $itemcontrato->id,
                    'producto_id' => $itemcontrato->producto_id,
                    'tipo_adquisicion' => $linea['tipo_adquisicion'] ?? 'bien',
                    'municipio_id' => $linea['municipio_id'] ?? null,
                    'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                    'valor_base' => $linea['valor_base'],
                    'valor_iva' => $linea['valor_iva'],
                    'valor_con_iva' => $linea['valor_con_iva'],
                    'cantidad' => 1,
                    'es_ajuste' => true,
                    'porcentaje_iva' => $linea['porcentaje_iva'] ?? null,
                ]);
            } else {
                // Línea normal: valores del itemcontrato
                $facturaLinea = FacturaLinea::create([
                    'factura_id' => $factura->id,
                    'itemcontrato_id' => $itemcontrato->id,
                    'producto_id' => $itemcontrato->producto_id,
                    'tipo_adquisicion' => $linea['tipo_adquisicion'] ?? 'bien',
                    'municipio_id' => $linea['municipio_id'] ?? null,
                    'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                    'valor_base' => $itemcontrato->valor_costo * $cantidad,
                    'valor_iva' => $itemcontrato->valor_iva * $cantidad,
                    'valor_con_iva' => $itemcontrato->valor_con_iva * $cantidad,
                    'cantidad' => $cantidad,
                ]);
            }

            // Calcular retenciones
            $resultado = $servicio->calcularYPersistir($facturaLinea);
            $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
            $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
        }

        // Calcular totales
        $totales = $this->totalFactura;
        $factura->update([
            'subtotal' => $totales['subtotal'],
            'total_iva' => $totales['total_iva'],
            'total_retenciones' => $totales['total_retenciones'],
            'total' => $totales['total'],
        ]);

        session()->flash('message', 'Factura ' . $factura->numero . ' creada correctamente.');
        $this->dispatch('alerta', tipo: 'success', mensaje: 'Factura ' . $factura->numero . ' creada correctamente.');
        $this->guardando = false;
    }

    // ------------------------------------------------------------------
    // PASO 2b: Editar factura existente
    // ------------------------------------------------------------------

    public function editarFactura(): void
    {
        if (!$this->factura_id || $this->guardando) return;

        $factura = Factura::find($this->factura_id);
        if (!$factura || $factura->estado !== 'borrador') {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Solo se pueden editar facturas en estado borrador.');
            return;
        }

        $this->guardando = true;

        if (empty($this->dependencia_id)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar una Dependencia / Comedor.');
            $this->guardando = false;
            return;
        }

        if (empty($this->lineas)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe agregar al menos una línea de producto.');
            $this->guardando = false;
            return;
        }

        foreach ($this->lineas as $idx => $linea) {
            if (($linea['tipo_adquisicion'] ?? 'bien') === 'servicio' && empty($linea['municipio_id'])) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'La línea "' . ($linea['producto_nombre'] ?? '') . '" es un servicio y debe tener un municipio.');
                $this->guardando = false;
                return;
            }
        }

        // Validar saldo de rubros
        $porItemcontrato = [];
        foreach ($this->lineas as $linea) {
            $itemcontratoId = $linea['itemcontrato_id'];
            $porItemcontrato[$itemcontratoId] = ($porItemcontrato[$itemcontratoId] ?? 0) + ($linea['valor_con_iva'] ?? 0);
        }

        foreach ($porItemcontrato as $itemcontratoId => $totalLineas) {
            $itemcontrato = Itemcontrato::with('movirubro')->find($itemcontratoId);
            if (!$itemcontrato || !$itemcontrato->movirubro) continue;

            $movirubro = $itemcontrato->movirubro;
            $saldoDisponible = (float) $movirubro->saldo_rubro;

            $facturasExistentes = \App\Models\FacturaLinea::whereHas('factura', function ($q) use ($movirubro) {
                $q->where('contrato_id', $this->contrato->id)
                  ->whereIn('estado', ['borrador', 'emitida'])
                  ->where('id', '!=', $this->factura_id);
            })->whereHas('itemcontrato', function ($q) use ($movirubro) {
                $q->where('movirubro_id', $movirubro->id);
            })->sum('valor_con_iva');

            $saldoRestante = $saldoDisponible - $facturasExistentes;

            if ($totalLineas > $saldoRestante + 0.01) {
                $nombreRubro = $movirubro->rubro->nombre_rubro ?? 'Sin nombre';
                $codigoRubro = $movirubro->rubro->codigo_rubro ?? '';
                $this->dispatch('alerta', tipo: 'error', mensaje: 'El rubro "' . $codigoRubro . ' - ' . $nombreRubro . '" no tiene saldo suficiente. Saldo disponible: $' . number_format($saldoRestante, 2, ',', '.') . ', total de la factura para este rubro: $' . number_format($totalLineas, 2, ',', '.'));
                $this->guardando = false;
                return;
            }
        }

        $servicio = new CalculadoraRetenciones();

        foreach ($this->lineas as $idx => $linea) {
            if (isset($linea['factura_linea_id']) && $linea['factura_linea_id']) {
                $facturaLinea = FacturaLinea::find($linea['factura_linea_id']);
                if (!$facturaLinea) continue;

                $cantidad = max(1, (float) ($linea['cantidad'] ?? 1));
                $esAjuste = $linea['es_ajuste'] ?? false;

                if ($esAjuste) {
                    // Línea de ajuste: usar valores personalizados
                    $facturaLinea->update([
                        'cantidad' => 1,
                        'municipio_id' => $linea['municipio_id'] ?? null,
                        'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                        'valor_base' => $linea['valor_base'],
                        'valor_iva' => $linea['valor_iva'],
                        'valor_con_iva' => $linea['valor_con_iva'],
                        'es_ajuste' => true,
                        'porcentaje_iva' => $linea['porcentaje_iva'] ?? null,
                    ]);
                } else {
                    // Línea normal: valores del itemcontrato
                    $facturaLinea->update([
                        'cantidad' => $cantidad,
                        'municipio_id' => $linea['municipio_id'] ?? null,
                        'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                        'valor_base' => $facturaLinea->itemcontrato->valor_costo * $cantidad,
                        'valor_iva' => $facturaLinea->itemcontrato->valor_iva * $cantidad,
                        'valor_con_iva' => $facturaLinea->itemcontrato->valor_con_iva * $cantidad,
                    ]);
                }

                $servicio->calcularYPersistir($facturaLinea);
            } else {
                $itemcontrato = Itemcontrato::find($linea['itemcontrato_id']);
                if (!$itemcontrato) continue;

                $cantidad = max(1, (float) ($linea['cantidad'] ?? 1));

                $facturaLinea = FacturaLinea::create([
                    'factura_id' => $factura->id,
                    'itemcontrato_id' => $itemcontrato->id,
                    'producto_id' => $itemcontrato->producto_id,
                    'tipo_adquisicion' => $linea['tipo_adquisicion'] ?? 'bien',
                    'municipio_id' => $linea['municipio_id'] ?? null,
                    'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                    'valor_base' => $itemcontrato->valor_costo * $cantidad,
                    'valor_iva' => $itemcontrato->valor_iva * $cantidad,
                    'valor_con_iva' => $itemcontrato->valor_con_iva * $cantidad,
                    'cantidad' => $cantidad,
                ]);

                $resultado = $servicio->calcularYPersistir($facturaLinea);
                $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
                $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
            }
        }

        $totales = $this->totalFactura;
        $factura->update([
            'municipio_id' => $this->municipio_default_id,
            'dependencia_id' => $this->dependencia_id,
            'numero_migo' => $this->numero_migo ?: null,
            'fecha_migo' => $this->fecha_migo ?: null,
            'subtotal' => $totales['subtotal'],
            'total_iva' => $totales['total_iva'],
            'total_retenciones' => $totales['total_retenciones'],
            'total' => $totales['total'],
        ]);

        $this->dispatch('alerta', tipo: 'success', mensaje: 'Factura actualizada correctamente.');
        $this->guardando = false;
    }

    // ------------------------------------------------------------------
    // Líneas: agregar/eliminar
    // ------------------------------------------------------------------

    public function agregarLinea(int $itemcontratoId): void
    {
        $item = Itemcontrato::with('producto')->find($itemcontratoId);
        if (!$item) return;

        // Servicio sin municipio asignado → advertencia
        if (($item->producto->tipo ?? 'bien') === 'servicio' && !$item->producto->municipio_id) {
            $this->dispatch('alerta', tipo: 'warning', mensaje: 'El servicio "' . $item->producto->name . '" no tiene municipio asignado. Seleccione un municipio en la línea para calcular Reteica.');
        }

        if ($this->editando && $this->factura_id) {
            // Servicio: usar municipio del producto si existe, null si no (usuario debe seleccionar)
            $municipioLinea = ($item->producto->tipo ?? 'bien') === 'servicio'
                ? $item->producto->municipio_id
                : $this->municipio_default_id;

            $facturaLinea = FacturaLinea::create([
                'factura_id' => $this->factura_id,
                'itemcontrato_id' => $item->id,
                'producto_id' => $item->producto_id,
                'tipo_adquisicion' => $item->producto->tipo ?? 'bien',
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $item->valor_costo,
                'valor_iva' => $item->valor_iva,
                'valor_con_iva' => $item->valor_con_iva,
                'cantidad' => 1,
            ]);

            $servicio = new CalculadoraRetenciones();
            $resultado = $servicio->calcularYPersistir($facturaLinea);

            $idx = count($this->lineas);
            $this->lineas[$idx] = [
                'factura_linea_id' => $facturaLinea->id,
                'itemcontrato_id' => $item->id,
                'producto_nombre' => $item->producto->name,
                'valor_costo_unit' => $item->valor_costo,
                'iva_unit' => $item->iva,
                'valor_iva_unit' => $item->valor_iva,
                'valor_con_iva_unit' => $item->valor_con_iva,
                'cantidad' => 1,
                'tipo_adquisicion' => $item->producto->tipo ?? 'bien',
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $item->valor_costo,
                'valor_iva' => $item->valor_iva,
                'valor_con_iva' => $item->valor_con_iva,
            ];

            $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
            $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
        } else {
            $municipioLinea = ($item->producto->tipo ?? 'bien') === 'servicio'
                ? $item->producto->municipio_id
                : $this->municipio_default_id;

            $this->lineas[] = [
                'itemcontrato_id' => $item->id,
                'producto_nombre' => $item->producto->name,
                'valor_costo_unit' => $item->valor_costo,
                'iva_unit' => $item->iva,
                'valor_iva_unit' => $item->valor_iva,
                'valor_con_iva_unit' => $item->valor_con_iva,
                'cantidad' => 1,
                'tipo_adquisicion' => $item->producto->tipo ?? 'bien',
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $item->valor_costo,
                'valor_iva' => $item->valor_iva,
                'valor_con_iva' => $item->valor_con_iva,
            ];

            $this->calcularRetencionesLinea(count($this->lineas) - 1);
        }
    }

    public function eliminarLinea(int $indice): void
    {
        if (!isset($this->lineas[$indice])) return;

        if ($this->factura_id) {
            $linea = FacturaLinea::where('factura_id', $this->factura_id)
                ->where('itemcontrato_id', $this->lineas[$indice]['itemcontrato_id'])
                ->first();
            if ($linea) {
                $linea->retenciones()->delete();
                $linea->delete();
            }
        }

        array_splice($this->lineas, $indice, 1);
        array_splice($this->retencionesPorLinea, $indice, 1);
        array_splice($this->pendientesPorLinea, $indice, 1);
    }

    // ------------------------------------------------------------------
    // Agregar línea de ajuste
    // ------------------------------------------------------------------

    public function agregarLineaAjuste(int $itemcontratoId): void
    {
        $item = Itemcontrato::with('producto')->find($itemcontratoId);
        if (!$item) return;

        if ($this->valorAjuste <= 0) {
            session()->flash('error', 'El valor del ajuste debe ser mayor a cero.');
            return;
        }

        if ($this->porcentajeIvaAjuste < 0 || $this->porcentajeIvaAjuste > 100) {
            session()->flash('error', 'El porcentaje de IVA debe estar entre 0 y 100.');
            return;
        }

        $tipo = $item->producto->tipo ?? 'bien';

        if ($tipo === 'servicio' && !$item->producto->municipio_id) {
            $this->dispatch('alerta', tipo: 'warning', mensaje: 'El servicio "' . $item->producto->name . '" no tiene municipio asignado. Seleccione un municipio en la línea para calcular Reteica.');
        }

        // Calcular base e IVA desde el valor total (con IVA)
        $divisor = 100 + $this->porcentajeIvaAjuste;
        $valorIva = round($this->valorAjuste * ($this->porcentajeIvaAjuste / $divisor), 2);
        $valorBase = round($this->valorAjuste - $valorIva, 2);

        $municipioLinea = ($item->producto->tipo ?? 'bien') === 'servicio'
            ? $item->producto->municipio_id
            : $this->municipio_default_id;

        if ($this->editando && $this->factura_id) {
            $facturaLinea = FacturaLinea::create([
                'factura_id' => $this->factura_id,
                'itemcontrato_id' => $item->id,
                'producto_id' => $item->producto_id,
                'tipo_adquisicion' => $tipo,
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $valorBase,
                'valor_iva' => $valorIva,
                'valor_con_iva' => $this->valorAjuste,
                'cantidad' => 1,
                'es_ajuste' => true,
                'porcentaje_iva' => $this->porcentajeIvaAjuste,
            ]);

            $servicio = new CalculadoraRetenciones();
            $resultado = $servicio->calcularYPersistir($facturaLinea);

            $idx = count($this->lineas);
            $this->lineas[$idx] = [
                'factura_linea_id' => $facturaLinea->id,
                'itemcontrato_id' => $item->id,
                'producto_nombre' => $item->producto->name,
                'valor_costo_unit' => $valorBase,
                'iva_unit' => $this->porcentajeIvaAjuste,
                'valor_iva_unit' => $valorIva,
                'valor_con_iva_unit' => $this->valorAjuste,
                'cantidad' => 1,
                'tipo_adquisicion' => $tipo,
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $valorBase,
                'valor_iva' => $valorIva,
                'valor_con_iva' => $this->valorAjuste,
                'es_ajuste' => true,
                'porcentaje_iva' => $this->porcentajeIvaAjuste,
            ];

            $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
            $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
        } else {
            $this->lineas[] = [
                'itemcontrato_id' => $item->id,
                'producto_nombre' => $item->producto->name,
                'valor_costo_unit' => $valorBase,
                'iva_unit' => $this->porcentajeIvaAjuste,
                'valor_iva_unit' => $valorIva,
                'valor_con_iva_unit' => $this->valorAjuste,
                'cantidad' => 1,
                'tipo_adquisicion' => $tipo,
                'municipio_id' => $municipioLinea,
                'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base' => $valorBase,
                'valor_iva' => $valorIva,
                'valor_con_iva' => $this->valorAjuste,
                'es_ajuste' => true,
                'porcentaje_iva' => $this->porcentajeIvaAjuste,
            ];

            $this->calcularRetencionesLinea(count($this->lineas) - 1);
        }

        // Reset
        $this->valorAjuste = 0;
        $this->porcentajeIvaAjuste = 19;
    }

    public function updatedLineas(): void
    {
        foreach ($this->lineas as $idx => $linea) {
            // Saltar recálculo para líneas de ajuste (valores fijos)
            if ($linea['es_ajuste'] ?? false) {
                $this->calcularRetencionesLinea($idx);
                continue;
            }
            $this->lineas[$idx]['valor_base'] = round($linea['valor_costo_unit'] * max(1, $linea['cantidad']), 2);
            $this->lineas[$idx]['valor_iva'] = round($linea['valor_iva_unit'] * max(1, $linea['cantidad']), 2);
            $this->lineas[$idx]['valor_con_iva'] = round($linea['valor_con_iva_unit'] * max(1, $linea['cantidad']), 2);
            $this->calcularRetencionesLinea($idx);
        }
    }

    public function calcularRetencionesLinea(int $idx): void
    {
        if (!isset($this->lineas[$idx])) return;

        $linea = $this->lineas[$idx];

        // Para ajustes: usar producto_id directamente
        $productoId = $linea['es_ajuste'] ?? false
            ? ($this->contrato->itemcontratos->firstWhere('producto_id', $linea['itemcontrato_id'] ?? null)?->producto_id ?? $linea['itemcontrato_id'] ?? null)
            : Itemcontrato::find($linea['itemcontrato_id'])?->producto_id;

        $facturaLinea = new FacturaLinea([
            'factura_id' => $this->factura_id ?? 0,
            'itemcontrato_id' => $linea['itemcontrato_id'] ?? null,
            'producto_id' => $productoId,
            'tipo_adquisicion' => $linea['tipo_adquisicion'] ?? 'bien',
            'municipio_id' => $linea['municipio_id'] ?? null,
            'estampilla_retencion_id' => $linea['estampilla_retencion_id'] ?? null,
            'valor_base' => $linea['valor_base'] ?? 0,
            'valor_iva' => $linea['valor_iva'] ?? 0,
            'valor_con_iva' => $linea['valor_con_iva'] ?? 0,
            'cantidad' => $linea['cantidad'] ?? 1,
        ]);

        $servicio = new CalculadoraRetenciones();
        $resultado = $servicio->calcular($facturaLinea);

        // Reasignar arrays completos para que Livewire detecte el cambio
        $retenciones = $this->retencionesPorLinea;
        $retenciones[$idx] = $resultado['calculadas'];
        $this->retencionesPorLinea = $retenciones;

        $pendientes = $this->pendientesPorLinea;
        $pendientes[$idx] = $resultado['pendientes'];
        $this->pendientesPorLinea = $pendientes;
    }

    public function updatedLineasTipoAdquisicion($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];
        $this->calcularRetencionesLinea($idx);
    }

    public function updatedLineasMunicipioId($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];
        $this->calcularRetencionesLinea($idx);
    }

    public function updatedMunicipioDefaultId(): void
    {
        if ($this->factura_id) return;

        $val = $this->municipio_default_id !== '' ? (int) $this->municipio_default_id : null;

        foreach ($this->lineas as $idx => $linea) {
            $this->lineas[$idx]['municipio_id'] = $val;
            $this->calcularRetencionesLinea($idx);
        }
    }

    public function updatedEstampillaDefaultId(): void
    {
        if ($this->factura_id) return;

        $val = $this->estampilla_default_id !== '' ? (int) $this->estampilla_default_id : null;

        foreach ($this->lineas as $idx => $linea) {
            $this->lineas[$idx]['estampilla_retencion_id'] = $val;
            $this->calcularRetencionesLinea($idx);
        }
    }

    public function updatedLineasEstampillaRetencionId($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];
        $this->calcularRetencionesLinea($idx);
    }

    public function updatedLineasCantidad($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];

        // Saltar recálculo para líneas de ajuste (valores fijos)
        if ($this->lineas[$idx]['es_ajuste'] ?? false) {
            $this->calcularRetencionesLinea($idx);
            return;
        }

        $this->lineas[$idx]['valor_base'] = round($this->lineas[$idx]['valor_costo_unit'] * max(1, $value), 2);
        $this->lineas[$idx]['valor_iva'] = round($this->lineas[$idx]['valor_iva_unit'] * max(1, $value), 2);
        $this->lineas[$idx]['valor_con_iva'] = round($this->lineas[$idx]['valor_con_iva_unit'] * max(1, $value), 2);
        $this->calcularRetencionesLinea($idx);
    }

    public function updatedFechaFactura(): void
    {
        // No necesita acción: el código interno se calcula en la vista Blade
    }

    // ------------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------------

    public function resetForm(): void
    {
        $this->reset(['numcontrato', 'contrato', 'contratoError', 'factura_id', 'numero_factura', 'fecha_factura', 'numero_migo', 'fecha_migo', 'municipio_default_id', 'estampilla_default_id', 'dependencia_id', 'lineas', 'retencionesPorLinea', 'pendientesPorLinea', 'editando', 'estadoFactura', 'esAjuste', 'valorAjuste', 'porcentajeIvaAjuste']);
        $this->resetValidation();
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $editando ? 'Editar Factura' : 'Crear Factura' }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $editando ? 'Modifique los datos de la factura.' : 'Genere facturas seleccionando productos asignados a un contrato.' }}</p>
    </div>

    <div x-data="{ show: false, tipo: '', mensaje: '' }"
         x-on:alerta.window="
             show = true;
             tipo = $event.detail.tipo;
             mensaje = $event.detail.mensaje;
             setTimeout(() => show = false, 6000);
         "
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-full max-w-2xl px-4"
         style="display: none;">
        <div class="flex items-center gap-3 rounded-xl shadow-2xl border px-5 py-4"
              :class="{
                  'bg-green-50 border-green-300 text-green-800 dark:bg-green-900/60 dark:border-green-600 dark:text-green-200': tipo === 'success',
                  'bg-rose-50 border-rose-300 text-rose-800 dark:bg-rose-900/60 dark:border-rose-600 dark:text-rose-200': tipo === 'error',
                  'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-900/60 dark:border-amber-600 dark:text-amber-200': tipo === 'warning'
              }">
            <template x-if="tipo === 'success'">
                <svg class="w-6 h-6 flex-shrink-0 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </template>
            <template x-if="tipo === 'error'">
                <svg class="w-6 h-6 flex-shrink-0 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </template>
            <template x-if="tipo === 'warning'">
                <svg class="w-6 h-6 flex-shrink-0 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            </template>
            <p class="flex-1 text-sm font-semibold" x-text="mensaje"></p>
            <button @click="show = false" class="flex-shrink-0 ml-2 opacity-50 hover:opacity-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- PASO 1: Buscar contrato --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">1. Buscar Contrato</h2>
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de contrato</label>
                <input type="text" wire:model="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: 010-009-2026" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" />
            </div>
            <button type="button" wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">Buscar</button>
        </div>
        @if ($contratoError)
            <p class="mt-2 text-sm text-rose-500">{{ $contratoError }}</p>
        @endif
        @if ($contrato)
            <div class="mt-4 rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700/60 px-4 py-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Contrato: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</span></p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Proveedor: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '-' }}</span></p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Saldo disponible: <span class="font-semibold text-emerald-600 dark:text-emerald-400">${{ number_format($this->saldoDisponible, 2, ',', '.') }}</span></p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Productos asignados: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->itemcontratos->count() }}</span></p>
            </div>
        @endif
    </div>

    @if ($contrato)
        {{-- PASO 2: Datos de la factura --}}
        @if (!$factura_id || $editando)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ $editando ? 'Datos de la Factura' : '2. Datos de la Factura' }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de factura</label>
                        <input type="text" wire:model="numero_factura" placeholder="Ej: 001" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" {{ $editando ? 'readonly' : '' }} />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Código interno: {{ $contrato->proveedor_id ?? '?' }}-{{ $numero_factura ?: '001' }}-{{ $fecha_factura ? date('Y', strtotime($fecha_factura)) : date('Y') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                        <input type="date" wire:model="fecha_factura" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" {{ $editando ? 'readonly' : '' }} />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio por defecto</label>
                        <select wire:model.live="municipio_default_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Ninguno</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }} ({{ $m->departamento }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° MIGO</label>
                        <input type="text" wire:model="numero_migo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" placeholder="Ej: 001" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha MIGO</label>
                        <input type="date" wire:model="fecha_migo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla por defecto</label>
                        <select wire:model.live="estampilla_default_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Ninguna</option>
                            @foreach ($this->estampillas as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dependencia / Comedor *</label>
                        <select wire:model="dependencia_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Ninguna</option>
                            @foreach ($this->dependencias as $dep)
                                <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        {{-- PASO 3: Seleccionar productos (itemcontratos) --}}
        @if (!$factura_id || $editando)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {{ $esAjuste ? 'Agregar Ajuste' : ($editando ? 'Agregar Productos del Contrato' : '3. Seleccionar Productos del Contrato') }}
                    </h2>
                    <button type="button" wire:click="$set('esAjuste', {{ $esAjuste ? 'false' : 'true' }})"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition
                            {{ $esAjuste
                                ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $esAjuste ? 'Modo Ajuste ACTIVO' : 'Modo Ajuste' }}
                    </button>
                </div>

                @if ($esAjuste)
                    {{-- Modo Ajuste --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Total (con IVA) *</label>
                            <input type="number" wire:model="valorAjuste" min="0" step="0.01" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300" placeholder="Ej: 50000" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">% IVA</label>
                            <input type="number" wire:model="porcentajeIvaAjuste" min="0" max="100" step="0.01" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300" />
                        </div>
                        <div class="lg:col-span-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Seleccione un producto de la tabla y haga clic en "Agregar Ajuste"</p>
                        </div>
                    </div>

                    @if ($valorAjuste > 0)
                        @php
                            $divisorIva = 100 + $porcentajeIvaAjuste;
                            $ivaPreview = round($valorAjuste * ($porcentajeIvaAjuste / $divisorIva), 2);
                            $basePreview = round($valorAjuste - $ivaPreview, 2);
                        @endphp
                        <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700">
                            <p class="text-sm font-medium text-amber-600 dark:text-amber-400 mb-2">Detalle del ajuste:</p>
                            <div class="grid grid-cols-3 gap-3 text-sm">
                                <div class="rounded-lg bg-white dark:bg-gray-800 px-3 py-2">
                                    <span class="text-xs text-gray-500">Subtotal (base)</span>
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($basePreview, 2, ',', '.') }}</p>
                                </div>
                                <div class="rounded-lg bg-white dark:bg-gray-800 px-3 py-2">
                                    <span class="text-xs text-gray-500">IVA ({{ $porcentajeIvaAjuste }}%)</span>
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($ivaPreview, 2, ',', '.') }}</p>
                                </div>
                                <div class="rounded-lg bg-white dark:bg-gray-800 px-3 py-2">
                                    <span class="text-xs text-gray-500">Total Ajuste</span>
                                    <p class="font-bold text-amber-700 dark:text-amber-400">$ {{ number_format($valorAjuste, 2, ',', '.') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left">Producto</th>
                                    <th class="px-4 py-3 text-left">Rubro</th>
                                    <th class="px-4 py-3 text-right">Valor Unit.</th>
                                    <th class="px-4 py-3 center">IVA %</th>
                                    <th class="px-4 py-3 text-right">Valor c/IVA</th>
                                    <th class="px-4 py-3 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($contrato->itemcontratos as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->producto->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                            {{ $item->rubro->codigo_rubro ?? '-' }} - {{ $item->rubro->nombre_rubro ?? '-' }}
                                            <span class="block text-[10px] text-emerald-500 dark:text-emerald-400">Saldo: ${{ number_format($item->movirubro?->saldo_rubro ?? 0, 2, ',', '.') }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->valor_costo, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 center text-gray-600 dark:text-gray-400">{{ $item->iva }}%</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($item->valor_con_iva, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button wire:click="agregarLineaAjuste({{ $item->id }})" class="px-3 py-1 text-xs font-medium rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 transition">
                                                + Agregar Ajuste
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No hay productos disponibles.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                @else
                    {{-- Modo Producto Normal --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left">Producto</th>
                                    <th class="px-4 py-3 text-left">Rubro</th>
                                    <th class="px-4 py-3 text-right">Valor Unit.</th>
                                    <th class="px-4 py-3 center">IVA %</th>
                                    <th class="px-4 py-3 text-right">Valor c/IVA</th>
                                    <th class="px-4 py-3 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @php
                                    $itemsDisponibles = $editando ? $this->itemcontratosDisponibles : $contrato->itemcontratos;
                                @endphp
                                @forelse ($itemsDisponibles as $item)
                                    @php
                                        $yaSeleccionado = !$editando && collect($this->lineas)->contains('itemcontrato_id', $item->id);
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->producto->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                            {{ $item->rubro->codigo_rubro ?? '-' }} - {{ $item->rubro->nombre_rubro ?? '-' }}
                                            <span class="block text-[10px] text-emerald-500 dark:text-emerald-400">Saldo: ${{ number_format($item->movirubro?->saldo_rubro ?? 0, 2, ',', '.') }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->valor_costo, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 center text-gray-600 dark:text-gray-400">{{ $item->iva }}%</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($item->valor_con_iva, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if ($yaSeleccionado)
                                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">✓ Seleccionado</span>
                                            @else
                                                <button wire:click="agregarLinea({{ $item->id }})" class="px-3 py-1 text-xs font-medium rounded-lg bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-400 transition">
                                                    Agregar
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No hay productos disponibles.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- PASO 4: Líneas de la factura --}}
        @if (count($this->lineas) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {{ $editando ? 'Líneas de la Factura' : '4. Configurar Líneas' }}
                    </h2>
                    @if ($editando)
                        <button wire:click="editarFactura" wire:confirm="¿Guardar cambios?" wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                            <span wire:loading.remove>Guardar Cambios</span>
                            <span wire:loading>Guardando...</span>
                        </button>
                    @else
                        <button wire:click="crearFactura" wire:confirm="¿Crear factura con las líneas configuradas?" wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition">
                            <span wire:loading.remove>Crear Factura</span>
                            <span wire:loading>Creando...</span>
                        </button>
                    @endif
                </div>

                @foreach ($this->lineas as $idx => $linea)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4 last:mb-0">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $linea['producto_nombre'] }}</h3>
                                @if ($linea['es_ajuste'] ?? false)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">AJUSTE</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ ($linea['tipo_adquisicion'] ?? 'bien') === 'bien' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ ($linea['tipo_adquisicion'] ?? 'bien') === 'bien' ? 'Bien' : 'Servicio' }}
                                </span>
                                <button wire:click="eliminarLinea({{ $idx }})" wire:confirm="¿Eliminar esta línea?" class="text-rose-500 hover:text-rose-700" title="Eliminar línea">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm mb-3">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Valor Unit:</span>
                                <span class="font-medium text-gray-800 dark:text-gray-100">${{ number_format($linea['valor_costo_unit'], 2, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">IVA:</span>
                                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $linea['iva_unit'] }}%</span>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Cantidad</label>
                                <input type="number" step="0.01" min="1" wire:model.live="lineas.{{ $idx }}.cantidad" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Municipio</label>
                                <select wire:model.live="lineas.{{ $idx }}.municipio_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                                    <option value="">Seleccionar...</option>
                                    @foreach ($this->municipios as $m)
                                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Estampilla</label>
                                <select wire:model.live="lineas.{{ $idx }}.estampilla_retencion_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                                    <option value="">Ninguna</option>
                                    @foreach ($this->estampillas as $e)
                                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-sm mb-3">
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Base</span>
                                <p class="font-semibold text-gray-800 dark:text-gray-100">${{ number_format($linea['valor_base'] ?? 0, 2, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">IVA</span>
                                <p class="font-semibold text-gray-800 dark:text-gray-100">${{ number_format($linea['valor_iva'] ?? 0, 2, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Total Línea</span>
                                <p class="font-semibold text-gray-800 dark:text-gray-100">${{ number_format($linea['valor_con_iva'] ?? 0, 2, ',', '.') }}</p>
                            </div>
                        </div>

                        {{-- Retenciones de esta línea --}}
                        @if (isset($this->retencionesPorLinea[$idx]) && count($this->retencionesPorLinea[$idx]) > 0)
                            <div class="mt-2">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Retenciones:</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->retencionesPorLinea[$idx] as $ret)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                            {{ $ret['retencion']->name }}: {{ $ret['porcentaje'] }}% → ${{ number_format($ret['valor_retenido'], 2, ',', '.') }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (isset($this->pendientesPorLinea[$idx]) && count($this->pendientesPorLinea[$idx]) > 0)
                            <div class="mt-2">
                                <p class="text-xs font-semibold text-amber-500 mb-1">Pendientes (falta configurar tarifa):</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->pendientesPorLinea[$idx] as $pend)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                            {{ $pend->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Resumen totales --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Resumen Factura</h2>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Subtotal</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">${{ number_format($this->totalFactura['subtotal'], 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">IVA</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">${{ number_format($this->totalFactura['total_iva'], 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
                        <p class="text-xs text-blue-500">Total sin retenciones</p>
                        <p class="text-lg font-bold text-blue-700 dark:text-blue-400">${{ number_format($this->totalFactura['subtotal'] + $this->totalFactura['total_iva'], 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 px-4 py-3">
                        <p class="text-xs text-rose-500">Retenciones</p>
                        <p class="text-lg font-bold text-rose-700 dark:text-rose-400">-${{ number_format($this->totalFactura['total_retenciones'], 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
                        <p class="text-xs text-emerald-500">Total</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">${{ number_format($this->totalFactura['total'], 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
