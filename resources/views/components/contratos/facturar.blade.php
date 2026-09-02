<?php

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Itemcontrato;
use App\Models\Municipio;
use App\Models\ReteicaTarifa;
use App\Services\CalculadoraRetenciones;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $numcontrato = '';
    public ?Contrato $contrato = null;
    public string $contratoError = '';

    // Datos de la factura
    public string $numero_factura = '';
    public string $fecha_factura = '';
    public string $numero_migo = '';
    public string $fecha_migo = '';
    public ?int $municipio_default_id = null;
    public string $estampilla_default_id = '';
    public ?int $dependencia_id = null;

    // Nota de crédito (opcional)
    public string $nota_credito = '';
    public ?float $nota_credito_valor = null;

    // Selección actual (antes de agregar)
    public ?int $producto_id = null;
    public int $cantidad = 1;
    public ?int $municipio_linea = null;
    public string $estampilla_linea_id = '';

    // Líneas agregadas
    public array $lineas = [];
    public array $retencionesPorLinea = [];
    public array $pendientesPorLinea = [];

    // Preview del producto seleccionado
    public ?array $productoSeleccionado = null;
    public float $subtotalLinea = 0;
    public float $ivaLinea = 0;
    public float $totalLinea = 0;

    // Modo ajuste
    public bool $esAjuste = false;
    public float $valorAjuste = 0;
    public float $porcentajeIvaAjuste = 19;

    // Guardado y edición
    public bool $guardando = false;
    public ?int $factura_id = null;
    public string $estadoFactura = 'borrador';

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->cargarFactura($id);
        }
    }

    public function getMunicipiosProperty()
    {
        return Municipio::orderBy('nombre')->get();
    }

    public function getEstampillasProperty()
    {
        return \App\Models\Retencion::where('tipo', 'territorial')
            ->whereHas('estampillaTarifas')
            ->orderBy('name')
            ->get();
    }

    public function getDependenciasProperty()
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
     * Indica si el municipio seleccionado tiene Reteica configurado para el proveedor.
     */
    public function getReteicaConfiguradoProperty(): bool
    {
        if (!$this->municipio_linea || !$this->contrato || !$this->contrato->proveedor) {
            return false;
        }

        return ReteicaTarifa::where('proveedor_id', $this->contrato->proveedor_id)
            ->where('municipio_id', $this->municipio_linea)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Buscar contrato
    // ------------------------------------------------------------------

    public function buscarContrato(): void
    {
        $this->contratoError = '';
        $this->contrato = null;
        $this->lineas = [];
        $this->retencionesPorLinea = [];
        $this->pendientesPorLinea = [];
        $this->productoSeleccionado = null;
        $this->producto_id = null;
        $this->cantidad = 1;
        $this->municipio_linea = null;
        $this->estampilla_linea_id = '';
        $this->esAjuste = false;
        $this->valorAjuste = 0;
        $this->porcentajeIvaAjuste = 19;

        if (empty(trim($this->numcontrato))) {
            $this->contratoError = 'Ingrese un número de contrato.';
            return;
        }

        $this->contrato = Contrato::with(['proveedor', 'movirubros.rubro', 'itemcontratos.producto'])
            ->where('numcontrato', trim($this->numcontrato))
            ->first();

        if (!$this->contrato) {
            $this->contratoError = 'No se encontró un contrato con ese número.';
            return;
        }

        $this->fecha_factura = now()->format('Y-m-d');

        $user = Auth::user();
        $this->municipio_default_id = $user->regional?->municipio_id ?? null;
        $this->municipio_linea = $this->municipio_default_id;
    }

    // ------------------------------------------------------------------
    // Preview del producto
    // ------------------------------------------------------------------

    public function calcularDetalleProducto(): void
    {
        if (!$this->producto_id || !$this->contrato) {
            $this->productoSeleccionado = null;
            $this->subtotalLinea = 0;
            $this->ivaLinea = 0;
            $this->totalLinea = 0;
            return;
        }

        $item = $this->contrato->itemcontratos->firstWhere('producto_id', $this->producto_id);

        if (!$item) {
            $this->productoSeleccionado = null;
            return;
        }

        $this->productoSeleccionado = [
            'producto_id'   => $item->producto_id,
            'nombre'        => $item->producto->name ?? '—',
            'tipo'          => $item->producto->tipo ?? 'bien',
            'valor_costo'   => $item->valor_costo,
            'iva'           => $item->iva,
            'valor_iva'     => $item->valor_iva,
            'valor_con_iva' => $item->valor_con_iva,
            'unidad'        => $item->unidad,
            'rubro'         => $item->rubro->nombre_rubro ?? '—',
            'uso'           => $item->producto->uso->nombre_uso ?? '—',
        ];

        // Si es servicio: auto-seleccionar municipio del producto o dejar en blanco
        if (($item->producto->tipo ?? 'bien') === 'servicio') {
            if ($item->producto->municipio_id) {
                $this->municipio_linea = $item->producto->municipio_id;
            } else {
                $this->municipio_linea = null;
                $this->dispatch('alerta', tipo: 'warning', mensaje: 'El servicio "' . $item->producto->name . '" no tiene municipio asignado. Seleccione un municipio para calcular Reteica.');
            }
        }

        $this->subtotalLinea = $item->valor_costo * $this->cantidad;
        $this->ivaLinea = $item->valor_iva * $this->cantidad;
        $this->totalLinea = $this->subtotalLinea + $this->ivaLinea;
    }

    // ------------------------------------------------------------------
    // Agregar / eliminar líneas
    // ------------------------------------------------------------------

    public function agregarLinea(): void
    {
        if (!$this->producto_id || !$this->contrato) {
            session()->flash('error', 'Seleccione un producto válido.');
            return;
        }

        if ($this->cantidad < 1) {
            session()->flash('error', 'La cantidad debe ser al menos 1.');
            return;
        }

        $item = $this->contrato->itemcontratos->firstWhere('producto_id', $this->producto_id);
        if (!$item) {
            session()->flash('error', 'Producto no encontrado en el contrato.');
            return;
        }

        $tipo = $item->producto->tipo ?? 'bien';

        if ($tipo === 'servicio' && empty($this->municipio_linea)) {
            session()->flash('error', 'Los servicios deben tener un municipio seleccionado para Reteica.');
            return;
        }

        $this->lineas[] = [
            'itemcontrato_id'         => $item->id,
            'producto_nombre'         => $item->producto->name ?? '—',
            'tipo_adquisicion'        => $tipo,
            'valor_costo_unit'        => $item->valor_costo,
            'iva_unit'                => $item->iva,
            'valor_iva_unit'          => $item->valor_iva,
            'valor_con_iva_unit'      => $item->valor_con_iva,
            'unidad'                  => $item->unidad,
            'rubro'                   => $item->rubro->nombre_rubro ?? '—',
            'uso'                     => $item->producto->uso->nombre_uso ?? '—',
            'cantidad'                => $this->cantidad,
            'municipio_id'            => $this->municipio_linea,
            'municipio_nombre'        => $this->obtenerNombreMunicipio($this->municipio_linea),
            'estampilla_retencion_id' => !empty($this->estampilla_linea_id) ? (int) $this->estampilla_linea_id : null,
            'estampilla_nombre'       => $this->obtenerNombreEstampilla($this->estampilla_linea_id),
            'valor_base'              => $item->valor_costo * $this->cantidad,
            'valor_iva'               => $item->valor_iva * $this->cantidad,
            'valor_con_iva'           => $item->valor_con_iva * $this->cantidad,
        ];

        $idx = count($this->lineas) - 1;
        $this->calcularRetencionesLinea($idx);

        // Reset selección
        $this->producto_id = null;
        $this->cantidad = 1;
        $this->municipio_linea = $this->municipio_default_id;
        $this->estampilla_linea_id = $this->estampilla_default_id;
        $this->productoSeleccionado = null;
        $this->subtotalLinea = 0;
        $this->ivaLinea = 0;
        $this->totalLinea = 0;

        $this->dispatch('reset-producto-selector');
    }

    // ------------------------------------------------------------------
    // Agregar línea de ajuste
    // ------------------------------------------------------------------

    public function agregarLineaAjuste(): void
    {
        if (!$this->producto_id || !$this->contrato) {
            session()->flash('error', 'Seleccione un producto válido para el ajuste.');
            return;
        }

        if ($this->valorAjuste <= 0) {
            session()->flash('error', 'El valor del ajuste debe ser mayor a cero.');
            return;
        }

        if ($this->porcentajeIvaAjuste < 0 || $this->porcentajeIvaAjuste > 100) {
            session()->flash('error', 'El porcentaje de IVA debe estar entre 0 y 100.');
            return;
        }

        $item = $this->contrato->itemcontratos->firstWhere('producto_id', $this->producto_id);
        if (!$item) {
            session()->flash('error', 'Producto no encontrado en el contrato.');
            return;
        }

        $tipo = $item->producto->tipo ?? 'bien';

        if ($tipo === 'servicio' && empty($this->municipio_linea)) {
            session()->flash('error', 'Los servicios deben tener un municipio seleccionado para Reteica.');
            return;
        }

        // Calcular base e IVA desde el valor total (con IVA)
        $divisor = 100 + $this->porcentajeIvaAjuste;
        $valorIva = round($this->valorAjuste * ($this->porcentajeIvaAjuste / $divisor), 2);
        $valorBase = round($this->valorAjuste - $valorIva, 2);

        $this->lineas[] = [
            'itemcontrato_id'         => $item->id,
            'producto_nombre'         => $item->producto->name ?? '—',
            'tipo_adquisicion'        => $tipo,
            'valor_costo_unit'        => $valorBase,
            'iva_unit'                => $this->porcentajeIvaAjuste,
            'valor_iva_unit'          => $valorIva,
            'valor_con_iva_unit'      => $this->valorAjuste,
            'unidad'                  => $item->unidad,
            'rubro'                   => $item->rubro->nombre_rubro ?? '—',
            'uso'                     => $item->producto->uso->nombre_uso ?? '—',
            'cantidad'                => 1,
            'municipio_id'            => $this->municipio_linea,
            'municipio_nombre'        => $this->obtenerNombreMunicipio($this->municipio_linea),
            'estampilla_retencion_id' => !empty($this->estampilla_linea_id) ? (int) $this->estampilla_linea_id : null,
            'estampilla_nombre'       => $this->obtenerNombreEstampilla($this->estampilla_linea_id),
            'valor_base'              => $valorBase,
            'valor_iva'               => $valorIva,
            'valor_con_iva'           => $this->valorAjuste,
            'es_ajuste'               => true,
            'porcentaje_iva'          => $this->porcentajeIvaAjuste,
        ];

        $idx = count($this->lineas) - 1;
        $this->calcularRetencionesLinea($idx);

        // Reset selección
        $this->producto_id = null;
        $this->cantidad = 1;
        $this->municipio_linea = $this->municipio_default_id;
        $this->estampilla_linea_id = $this->estampilla_default_id;
        $this->productoSeleccionado = null;
        $this->subtotalLinea = 0;
        $this->ivaLinea = 0;
        $this->totalLinea = 0;
        $this->valorAjuste = 0;
        $this->porcentajeIvaAjuste = 19;

        $this->dispatch('reset-producto-selector');
    }

    public function eliminarLinea(int $indice): void
    {
        if (!isset($this->lineas[$indice])) return;

        array_splice($this->lineas, $indice, 1);
        array_splice($this->retencionesPorLinea, $indice, 1);
        array_splice($this->pendientesPorLinea, $indice, 1);
    }

    private function obtenerNombreMunicipio($id): string
    {
        if (!$id) return '—';
        $m = $this->municipios->firstWhere('id', $id);
        return $m ? $m->nombre : '—';
    }

    private function obtenerNombreEstampilla($id): string
    {
        if (!$id) return 'Ninguna';
        $e = $this->estampillas->firstWhere('id', $id);
        return $e ? $e->name : 'Ninguna';
    }

    // ------------------------------------------------------------------
    // Retenciones por línea
    // ------------------------------------------------------------------

    public function calcularRetencionesLinea(int $idx): void
    {
        if (!isset($this->lineas[$idx])) return;

        $linea = $this->lineas[$idx];

        // Para ajustes: usar producto_id directamente (no hay itemcontrato real)
        $productoId = $linea['es_ajuste'] ?? false
            ? ($this->contrato->itemcontratos->firstWhere('producto_id', $linea['itemcontrato_id'] ?? null)?->producto_id ?? $linea['itemcontrato_id'] ?? null)
            : Itemcontrato::find($linea['itemcontrato_id'])?->producto_id;

        $facturaLinea = new FacturaLinea([
            'factura_id'              => 0,
            'itemcontrato_id'         => $linea['itemcontrato_id'] ?? null,
            'producto_id'             => $productoId,
            'tipo_adquisicion'        => $linea['tipo_adquisicion'] ?? 'bien',
            'municipio_id'            => $linea['municipio_id'] ?? null,
            'estampilla_retencion_id' => $linea['estampilla_retencion_id'] ?? null,
            'valor_base'              => $linea['valor_base'] ?? 0,
            'valor_iva'               => $linea['valor_iva'] ?? 0,
            'valor_con_iva'           => $linea['valor_con_iva'] ?? 0,
            'cantidad'                => $linea['cantidad'] ?? 1,
        ]);

        $servicio = new CalculadoraRetenciones();
        $resultado = $servicio->calcular($facturaLinea);

        $retenciones = $this->retencionesPorLinea;
        $retenciones[$idx] = $resultado['calculadas'];
        $this->retencionesPorLinea = $retenciones;

        $pendientes = $this->pendientesPorLinea;
        $pendientes[$idx] = $resultado['pendientes'];
        $this->pendientesPorLinea = $pendientes;
    }

    // ------------------------------------------------------------------
    // Hooks
    // ------------------------------------------------------------------

    public function updatedMunicipioDefaultId(): void
    {
        $val = $this->municipio_default_id !== '' ? (int) $this->municipio_default_id : null;
        $this->municipio_linea = $val;
    }

    public function updatedEstampillaDefaultId(): void
    {
        $this->estampilla_linea_id = $this->estampilla_default_id;
    }

    public function updatedProductoId(): void
    {
        $this->calcularDetalleProducto();
    }

    public function updatedCantidad(): void
    {
        $this->calcularDetalleProducto();
    }

    // ------------------------------------------------------------------
    // Totales
    // ------------------------------------------------------------------

    public function getTotalFacturaProperty(): array
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
            'subtotal'          => $subtotal,
            'total_iva'         => $totalIva,
            'total_retenciones' => $totalRetenciones,
            'total'             => $subtotal + $totalIva - $totalRetenciones,
        ];
    }

    // ------------------------------------------------------------------
    // Guardar factura
    // ------------------------------------------------------------------

    public function grabarFactura(): void
    {
        if (!$this->contrato || $this->guardando) return;

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

        if (empty($this->lineas)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe agregar al menos una línea de producto.');
            $this->guardando = false;
            return;
        }

        if (empty($this->dependencia_id)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar una Dependencia / Comedor.');
            $this->guardando = false;
            return;
        }

        // Validar servicios sin municipio
        foreach ($this->lineas as $idx => $linea) {
            if (($linea['tipo_adquisicion'] ?? 'bien') === 'servicio' && empty($linea['municipio_id'])) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'La línea "' . ($linea['producto_nombre'] ?? '') . '" es un servicio y debe tener un municipio.');
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

            // Descontar facturas existentes (borrador + emitida) que usen este mismo movirubro, excluyendo la factura actual si se está editando
            $facturasExistentes = \App\Models\FacturaLinea::whereHas('factura', function ($q) use ($movirubro) {
                $q->where('contrato_id', $this->contrato->id)
                  ->whereIn('estado', ['borrador', 'emitida']);
                if ($this->factura_id) {
                    $q->where('id', '!=', $this->factura_id);
                }
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
        $year = date('Y', strtotime($this->fecha_factura));

        if ($this->factura_id) {
            // Editar factura existente
            $factura = Factura::find($this->factura_id);
            if (!$factura || $factura->estado !== 'borrador') {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'Solo se pueden editar facturas en estado borrador.');
                $this->guardando = false;
                return;
            }

            $numeroInterno = $this->contrato->proveedor_id . '-' . trim($this->numero_factura) . '-' . $year;

            // Verificar duplicado排除ándose a sí misma
            $existe = Factura::where('numero', $numeroInterno)->where('id', '!=', $this->factura_id)->exists();
            if ($existe) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'Ya existe otra factura con ese número.');
                $this->guardando = false;
                return;
            }

            // Eliminar líneas y retenciones anteriores
            foreach ($factura->lineas as $lineaExistente) {
                $lineaExistente->retenciones()->delete();
                $lineaExistente->delete();
            }

            $factura->update([
                'numero'        => $numeroInterno,
                'fecha'         => $this->fecha_factura,
                'numero_migo'   => $this->numero_migo ?: null,
                'fecha_migo'    => $this->fecha_migo ?: null,
                'municipio_id'  => $this->municipio_default_id,
                'dependencia_id' => $this->dependencia_id,
                'nota_credito'       => $this->nota_credito ?: null,
                'nota_credito_valor' => $this->nota_credito_valor,
            ]);

        } else {
            // Crear factura nueva
            $numeroInterno = $this->contrato->proveedor_id . '-' . trim($this->numero_factura) . '-' . $year;

            $existe = Factura::where('numero', $numeroInterno)->exists();
            if ($existe) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'Ya existe una factura con ese número para este proveedor.');
                $this->guardando = false;
                return;
            }

            $factura = Factura::create([
                'proveedor_id'   => $this->contrato->proveedor_id,
                'contrato_id'    => $this->contrato->id,
                'numero'         => $numeroInterno,
                'numero_migo'    => $this->numero_migo ?: null,
                'fecha_migo'     => $this->fecha_migo ?: null,
                'fecha'          => $this->fecha_factura,
                'municipio_id'   => $this->municipio_default_id,
                'dependencia_id' => $this->dependencia_id,
                'nota_credito'       => $this->nota_credito ?: null,
                'nota_credito_valor' => $this->nota_credito_valor,
                'estado'         => 'borrador',
            ]);

            $this->factura_id = $factura->id;
        }

        // Crear líneas
        foreach ($this->lineas as $idx => $linea) {
            $itemcontrato = Itemcontrato::find($linea['itemcontrato_id']);
            if (!$itemcontrato) continue;

            $cantidad = max(1, (float) ($linea['cantidad'] ?? 1));
            $esAjuste = $linea['es_ajuste'] ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores personalizados
                $facturaLinea = FacturaLinea::create([
                    'factura_id'              => $factura->id,
                    'itemcontrato_id'         => $itemcontrato->id,
                    'producto_id'             => $itemcontrato->producto_id,
                    'tipo_adquisicion'        => $linea['tipo_adquisicion'] ?? 'bien',
                    'municipio_id'            => $linea['municipio_id'] ?? null,
                    'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
                    'valor_base'              => $linea['valor_base'],
                    'valor_iva'               => $linea['valor_iva'],
                    'valor_con_iva'           => $linea['valor_con_iva'],
                    'cantidad'                => 1,
                    'es_ajuste'               => true,
                    'porcentaje_iva'          => $linea['porcentaje_iva'] ?? null,
                ]);
            } else {
                // Línea normal: valores del itemcontrato
        $facturaLinea = FacturaLinea::create([
        'factura_id'              => $factura->id,
        'itemcontrato_id'         => $itemcontrato->id,
        'producto_id'             => $itemcontrato->producto_id,
        'tipo_adquisicion'        => $linea['tipo_adquisicion'] ?? 'bien',
        'municipio_id'            => $linea['municipio_id'] ?? null,
        'estampilla_retencion_id' => !empty($linea['estampilla_retencion_id']) ? (int) $linea['estampilla_retencion_id'] : null,
        'valor_base'              => $itemcontrato->valor_costo * $cantidad,
        'valor_iva'               => $itemcontrato->valor_iva * $cantidad,
        'valor_con_iva'           => $itemcontrato->valor_con_iva * $cantidad,
        'cantidad'                => $cantidad,
    ]);
            }

            $resultado = $servicio->calcularYPersistir($facturaLinea);
            $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
            $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
        }

        // Calcular y guardar totales
        $totales = $this->totalFactura;
        $factura->update([
            'subtotal'          => $totales['subtotal'],
            'total_iva'         => $totales['total_iva'],
            'total_retenciones' => $totales['total_retenciones'],
            'total'             => $totales['total'],
        ]);

        $this->estadoFactura = $factura->estado;

        $mensaje = $this->factura_id && str_contains(request()->header('referer', ''), 'editar')
            ? 'Factura actualizada correctamente.'
            : 'Factura ' . $factura->numero . ' creada correctamente.';

        $this->dispatch('alerta', tipo: 'success', mensaje: $mensaje);
        $this->guardando = false;
    }

    // ------------------------------------------------------------------
    // Cargar factura para editar
    // ------------------------------------------------------------------

    public function cargarFactura(int $facturaId): void
    {
        $factura = Factura::with(['lineas.retenciones', 'contrato.proveedor', 'contrato.movirubros.rubro', 'contrato.itemcontratos.producto'])->find($facturaId);

        if (!$factura) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Factura no encontrada.');
            return;
        }

        if ($factura->estado !== 'borrador') {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Solo se pueden editar facturas en estado borrador.');
            return;
        }

        $this->contrato = $factura->contrato;
        $this->numcontrato = $factura->contrato->numcontrato;
        $this->factura_id = $factura->id;
        // Extraer el número del formato '{proveedor_id}-{num}-{year}'
        $partes = explode('-', $factura->numero);
        $this->numero_factura = $partes[1] ?? $factura->numero;
        $this->fecha_factura = $factura->fecha->format('Y-m-d');
        $this->numero_migo = $factura->numero_migo ?? '';
        $this->fecha_migo = $factura->fecha_migo ? $factura->fecha_migo->format('Y-m-d') : '';
        $this->municipio_default_id = $factura->municipio_id;
        $this->dependencia_id = $factura->dependencia_id;
        $this->nota_credito = $factura->nota_credito ?? '';
        $this->nota_credito_valor = $factura->nota_credito_valor;
        $this->estadoFactura = $factura->estado;

        // Cargar estampilla de la primera línea
        $primeraLinea = $factura->lineas->first();
        $this->estampilla_default_id = $primeraLinea && $primeraLinea->estampilla_retencion_id
            ? (string) $primeraLinea->estampilla_retencion_id
            : '';

        // Cargar líneas
        $this->lineas = [];
        $this->retencionesPorLinea = [];
        $this->pendientesPorLinea = [];

        foreach ($factura->lineas as $idx => $fl) {
            $item = $fl->itemcontrato;
            $esAjuste = $fl->es_ajuste ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores guardados (no del itemcontrato)
                $this->lineas[] = [
                    'itemcontrato_id'         => $fl->itemcontrato_id,
                    'producto_nombre'         => $item->producto->name ?? '—',
                    'tipo_adquisicion'        => $fl->tipo_adquisicion ?? 'bien',
                    'valor_costo_unit'        => $fl->valor_base,
                    'iva_unit'                => $fl->porcentaje_iva ?? 0,
                    'valor_iva_unit'          => $fl->valor_iva,
                    'valor_con_iva_unit'      => $fl->valor_con_iva,
                    'unidad'                  => $item->unidad,
                    'rubro'                   => $item->rubro->nombre_rubro ?? '—',
                    'uso'                     => $item->producto->uso->nombre_uso ?? '—',
                    'cantidad'                => $fl->cantidad,
                    'municipio_id'            => $fl->municipio_id,
                    'municipio_nombre'        => $this->obtenerNombreMunicipio($fl->municipio_id),
                    'estampilla_retencion_id' => $fl->estampilla_retencion_id,
                    'estampilla_nombre'       => $this->obtenerNombreEstampilla($fl->estampilla_retencion_id),
                    'valor_base'              => $fl->valor_base,
                    'valor_iva'               => $fl->valor_iva,
                    'valor_con_iva'           => $fl->valor_con_iva,
                    'es_ajuste'               => true,
                    'porcentaje_iva'          => $fl->porcentaje_iva,
                ];
            } else {
                // Línea normal: valores del itemcontrato
                $this->lineas[] = [
                    'itemcontrato_id'         => $fl->itemcontrato_id,
                    'producto_nombre'         => $item->producto->name ?? '—',
                    'tipo_adquisicion'        => $fl->tipo_adquisicion ?? 'bien',
                    'valor_costo_unit'        => $item->valor_costo,
                    'iva_unit'                => $item->iva,
                    'valor_iva_unit'          => $item->valor_iva,
                    'valor_con_iva_unit'      => $item->valor_con_iva,
                    'unidad'                  => $item->unidad,
                    'rubro'                   => $item->rubro->nombre_rubro ?? '—',
                    'uso'                     => $item->producto->uso->nombre_uso ?? '—',
                    'cantidad'                => $fl->cantidad,
                    'municipio_id'            => $fl->municipio_id,
                    'municipio_nombre'        => $this->obtenerNombreMunicipio($fl->municipio_id),
                    'estampilla_retencion_id' => $fl->estampilla_retencion_id,
                    'estampilla_nombre'       => $this->obtenerNombreEstampilla($fl->estampilla_retencion_id),
                    'valor_base'              => $fl->valor_base,
                    'valor_iva'               => $fl->valor_iva,
                    'valor_con_iva'           => $fl->valor_con_iva,
                ];
            }

            // Cargar retenciones guardadas
            $this->retencionesPorLinea[$idx] = $fl->retenciones->map(fn($r) => [
                'retencion'       => $r->retencion,
                'porcentaje'      => $r->porcentaje_aplicado,
                'valor_retenido'  => $r->valor_retenido,
            ])->toArray();

            $this->pendientesPorLinea[$idx] = [];
        }

        $this->dispatch('alerta', tipo: 'success', mensaje: 'Factura cargada para edición.');
    }

    // ------------------------------------------------------------------
    // Nueva factura (reset)
    // ------------------------------------------------------------------

    public function nuevaFactura(): void
    {
        $this->reset(['factura_id', 'numero_factura', 'fecha_factura', 'numero_migo', 'fecha_migo', 'municipio_default_id', 'estampilla_default_id', 'dependencia_id', 'nota_credito', 'nota_credito_valor', 'lineas', 'retencionesPorLinea', 'pendientesPorLinea', 'estadoFactura', 'esAjuste', 'valorAjuste', 'porcentajeIvaAjuste']);
        $this->estadoFactura = 'borrador';

        if ($this->contrato) {
            $this->fecha_factura = now()->format('Y-m-d');
            $user = Auth::user();
            $this->municipio_default_id = $user->regional?->municipio_id ?? null;
        }
    }

    public function render()
    {
        return view('components.contratos.facturar');
    }
};
?>

<div>
    <div class="flex items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Facturar</h1>
        @if ($factura_id)
            <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ ($estadoFactura === 'borrador' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                   : ($estadoFactura === 'emitida' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                   : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400')) }}">
                {{ ucfirst($estadoFactura) }}
            </span>
        @endif
    </div>

    {{-- Toast de alertas --}}
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
            <p class="text-sm font-medium" x-text="mensaje"></p>
        </div>
    </div>

    {{-- Buscar contrato --}}
    @if (!$factura_id)
        <div class="flex justify-center">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 shadow-md rounded-lg p-8">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-6 text-center">Buscar Contrato</h2>
                <form wire:submit.prevent="buscarContrato">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Contrato</label>
                        <input type="text" wire:model="numcontrato" class="form-input w-full" placeholder="Ej: 010-010-2026" autofocus />
                    </div>
                    @if ($contratoError)
                        <div class="mb-4 text-sm text-rose-500">{{ $contratoError }}</div>
                    @endif
                    <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-medium py-2 px-4 rounded-lg transition">
                        Buscar
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($contrato)
        <div class="mt-6 max-w-6xl mx-auto">

            {{-- Datos del contrato --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Datos del Contrato</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Número</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '—' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Objeto</p>
                        <p class="text-gray-800 dark:text-gray-100">{{ $contrato->objetocontrato }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Valor Total</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($contrato->valorTotal, 2, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Saldo Disponible</p>
                        <p class="font-semibold text-emerald-600 dark:text-emerald-400">$ {{ number_format($contrato->saldo, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Rubros --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden mb-6">
                <div class="p-6 pb-0">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Rubros</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-center px-3 py-3 font-medium text-gray-500 dark:text-gray-400 w-10">#</th>
                                <th class="text-left px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Código</th>
                                <th class="text-left px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Valor</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contrato->movirubros as $idx => $movirubro)
                                @php
                                    $duplicados = $contrato->movirubros->filter(fn($m) => $m->rubro_id === $movirubro->rubro_id)->count();
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $duplicados > 1 ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                                    <td class="px-3 py-3 text-center font-semibold {{ $duplicados > 1 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">{{ $idx + 1 }}</td>
                                    <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $movirubro->rubro->codigo_rubro ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-800 dark:text-gray-100">
                                        {{ $movirubro->rubro->nombre_rubro ?? '—' }}
                                        @if ($duplicados > 1)
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">DUP #{{ $idx + 1 }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($movirubro->valor_rubro, 2, ',', '.') }}</td>
                                    <td class="px-6 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">$ {{ number_format($movirubro->saldo_rubro, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No hay rubros registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Datos de la factura (selectores globales) --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Datos de la Factura</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° Factura</label>
                        <input type="text" wire:model="numero_factura" class="form-input w-full" placeholder="Ej: 001" />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Código interno: {{ $contrato->proveedor_id ?? '?' }}-{{ $numero_factura ?: '001' }}-{{ $fecha_factura ? date('Y', strtotime($fecha_factura)) : date('Y') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                        <input type="date" wire:model="fecha_factura" class="form-input w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio por defecto</label>
                        <select wire:model.live="municipio_default_id" class="form-select w-full">
                            <option value="">Ninguno</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }} ({{ $m->departamento }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° MIGO</label>
                        <input type="text" wire:model="numero_migo" class="form-input w-full" placeholder="Ej: 001" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha MIGO</label>
                        <input type="date" wire:model="fecha_migo" class="form-input w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla por defecto</label>
                        <select wire:model.live="estampilla_default_id" class="form-select w-full">
                            <option value="">Ninguna</option>
                            @foreach ($this->estampillas as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dependencia / Comedor *</label>
                        <select wire:model="dependencia_id" class="form-select w-full">
                            <option value="">Ninguna</option>
                            @foreach ($this->dependencias as $dep)
                                <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° Nota Crédito</label>
                        <input type="text" wire:model="nota_credito" class="form-input w-full" placeholder="Opcional" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Nota Crédito</label>
                        <input type="number" wire:model="nota_credito_valor" min="0" step="0.01" class="form-input w-full" placeholder="0.00" />
                    </div>
                </div>
            </div>

            {{-- Selección de producto (con selects por línea) --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                        {{ $esAjuste ? 'Agregar Ajuste' : 'Agregar Producto' }}
                    </h3>
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Producto (para retenciones) *</label>
                            @php
                                $productosJson = $contrato->itemcontratos->map(fn($it) => ['id' => $it->producto_id, 'name' => $it->producto->name ?? '—', 'rubro' => $it->rubro->codigo_rubro ?? '—', 'valor' => $it->valor_con_iva, 'saldo' => $it->movirubro?->saldo_rubro ?? 0])->values()->toArray();
                            @endphp
                            <div x-data="{ open: false, search: '', selectedId: @js($producto_id), selectedName: @js($producto_id ? ($contrato->itemcontratos->firstWhere('producto_id', $producto_id)?->producto->name ?? '') : ''), allProducts: @js($productosJson) }"
                                 wire:ignore
                                 @reset-producto-selector.window="selectedId = null; selectedName = ''; search = ''"
                                 @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open; search = ''" class="form-input w-full cursor-pointer flex items-center justify-between min-h-[38px]">
                                    <span x-text="selectedName || 'Seleccione producto...'" :class="selectedId ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'"></span>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div class="p-2 sticky top-0 bg-white dark:bg-gray-800 z-10">
                                        <input type="text" x-model="search" @click.stop placeholder="Escriba para buscar..." class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400" />
                                    </div>
                                    <template x-for="(p, idx) in (search ? allProducts.filter(x => x.name.toLowerCase().includes(search.toLowerCase()) || x.rubro.toLowerCase().includes(search.toLowerCase())) : allProducts)" :key="idx">
                                        <div @click.stop="selectedId = p.id; selectedName = p.name; open = false; search = ''; $wire.set('producto_id', p.id); $wire.calcularDetalleProducto()" class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 cursor-pointer flex items-center justify-between">
                                            <div>
                                                <span class="text-gray-800 dark:text-gray-100" x-text="p.name"></span>
                                                <span class="block text-xs text-purple-500 dark:text-purple-400" x-text="p.rubro"></span>
                                            </div>
                                            <div class="text-right ml-2">
                                                <span class="block text-xs text-gray-400 whitespace-nowrap" x-text="'$' + Number(p.valor).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                <span class="block text-[10px] text-emerald-500 dark:text-emerald-400 whitespace-nowrap" x-text="'Saldo: $' + Number(p.saldo).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Total (con IVA) *</label>
                            <input type="number" wire:model="valorAjuste" min="0" step="0.01" class="form-input w-full" placeholder="Ej: 50000" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">% IVA</label>
                            <input type="number" wire:model="porcentajeIvaAjuste" min="0" max="100" step="0.01" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Municipio
                                @if ($this->productoSeleccionado && ($this->productoSeleccionado['tipo'] ?? '') === 'servicio')
                                    <span class="text-amber-500 font-semibold">*</span>
                                @endif
                            </label>
                            <select wire:model.live="municipio_linea" class="form-select w-full">
                                <option value="">Seleccionar...</option>
                                @foreach ($this->municipios as $m)
                                    <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla</label>
                            <select wire:model="estampilla_linea_id" class="form-select w-full">
                                <option value="">Ninguna</option>
                                @foreach ($this->estampillas as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2 flex gap-2">
                            <button type="button" wire:click="agregarLineaAjuste" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-medium py-2 px-6 rounded-lg transition">
                                + Agregar Ajuste
                            </button>
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

                @else
                    {{-- Modo Producto Normal --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Producto</label>
                            @php
                                $productosJson = $contrato->itemcontratos->map(fn($it) => ['id' => $it->producto_id, 'name' => $it->producto->name ?? '—', 'rubro' => $it->rubro->codigo_rubro ?? '—', 'valor' => $it->valor_con_iva, 'saldo' => $it->movirubro?->saldo_rubro ?? 0, 'movirubro_id' => $it->movirubro_id])->values()->toArray();
                            @endphp
                            <div x-data="{ open: false, search: '', selectedId: @js($producto_id), selectedName: @js($producto_id ? ($contrato->itemcontratos->firstWhere('producto_id', $producto_id)?->producto->name ?? '') : ''), allProducts: @js($productosJson) }"
                                 wire:ignore
                                 @reset-producto-selector.window="selectedId = null; selectedName = ''; search = ''"
                                 @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open; search = ''" class="form-input w-full cursor-pointer flex items-center justify-between min-h-[38px]">
                                    <span x-text="selectedName || 'Seleccione producto...'" :class="selectedId ? 'text-gray-800 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'"></span>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <div class="p-2 sticky top-0 bg-white dark:bg-gray-800 z-10">
                                        <input type="text" x-model="search" @click.stop placeholder="Escriba para buscar..." class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-violet-400" />
                                    </div>
                                    <template x-for="(p, idx) in (search ? allProducts.filter(x => x.name.toLowerCase().includes(search.toLowerCase()) || x.rubro.toLowerCase().includes(search.toLowerCase())) : allProducts)" :key="idx">
                                        <div @click.stop="selectedId = p.id; selectedName = p.name; open = false; search = ''; $wire.set('producto_id', p.id); $wire.calcularDetalleProducto()" class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 cursor-pointer flex items-center justify-between">
                                            <div>
                                                <span class="text-gray-800 dark:text-gray-100" x-text="p.name"></span>
                                                <span class="block text-xs text-purple-500 dark:text-purple-400" x-text="p.rubro"></span>
                                            </div>
                                            <div class="text-right ml-2">
                                                <span class="block text-xs text-gray-400 whitespace-nowrap" x-text="'$' + Number(p.valor).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                                <span class="block text-[10px] text-emerald-500 dark:text-emerald-400 whitespace-nowrap" x-text="'Saldo: $' + Number(p.saldo).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad</label>
                            <input type="number" wire:model="cantidad" wire:change="calcularDetalleProducto" min="1" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Municipio
                                @if ($this->productoSeleccionado && ($this->productoSeleccionado['tipo'] ?? '') === 'servicio')
                                    <span class="text-amber-500 font-semibold">*</span>
                                @endif
                            </label>
                            <select wire:model.live="municipio_linea" class="form-select w-full">
                                <option value="">Seleccionar...</option>
                                @foreach ($this->municipios as $m)
                                    <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            @if ($this->productoSeleccionado && ($this->productoSeleccionado['tipo'] ?? '') === 'servicio' && !$this->reteicaConfigurado)
                                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">Requerido para Reteica</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla</label>
                            <select wire:model="estampilla_linea_id" class="form-select w-full">
                                <option value="">Ninguna</option>
                                @foreach ($this->estampillas as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2 flex gap-2">
                            <button type="button" wire:click="agregarLinea" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-6 rounded-lg transition">
                                + Agregar
                            </button>
                        </div>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mt-3 text-sm text-rose-500">{{ session('error') }}</div>
                @endif

                {{-- Preview del producto --}}
                @if ($this->productoSeleccionado)
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Detalle del producto seleccionado:</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Tipo:</span>
                                <p class="font-medium">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $this->productoSeleccionado['tipo'] === 'bien' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        {{ ucfirst($this->productoSeleccionado['tipo']) }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Valor Unitario:</span>
                                <p class="font-medium text-gray-800 dark:text-gray-100">$ {{ number_format($this->productoSeleccionado['valor_costo'], 2, ',', '.') }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">IVA Unitario:</span>
                                <p class="font-medium text-gray-800 dark:text-gray-100">$ {{ number_format($this->productoSeleccionado['valor_iva'], 2, ',', '.') }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Rubro:</span>
                                <p class="font-medium text-gray-800 dark:text-gray-100">{{ $this->productoSeleccionado['rubro'] }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm mt-3">
                            <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 px-3 py-2">
                                <span class="text-xs text-violet-500">Subtotal</span>
                                <p class="font-semibold text-violet-700 dark:text-violet-400">$ {{ number_format($this->subtotalLinea, 2, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 px-3 py-2">
                                <span class="text-xs text-violet-500">IVA Total</span>
                                <p class="font-semibold text-violet-700 dark:text-violet-400">$ {{ number_format($this->ivaLinea, 2, ',', '.') }}</p>
                            </div>
                            <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 px-3 py-2">
                                <span class="text-xs text-violet-500">Total Línea</span>
                                <p class="font-bold text-violet-700 dark:text-violet-400">$ {{ number_format($this->totalLinea, 2, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Líneas de la factura --}}
            @if (count($lineas) > 0)
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden mb-6">
                    <div class="p-6 pb-0">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Líneas de Factura</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-center px-3 py-3 font-medium text-gray-500 dark:text-gray-400 w-10">#</th>
                                    <th class="text-left px-4 py-3 font-medium text-blue-500 dark:text-blue-400">Producto</th>
                                    <th class="text-right px-4 py-3 font-medium text-gray-500 dark:text-gray-400">Cant.</th>
                                    <th class="text-right px-4 py-3 font-medium text-blue-500 dark:text-blue-400">V. Unitario</th>
                                    <th class="text-right px-4 py-3 font-medium text-blue-500 dark:text-blue-400">IVA Unit.</th>
                                    <th class="text-right px-4 py-3 font-medium text-blue-500 dark:text-blue-400">V. c/IVA</th>
                                    <th class="text-right px-4 py-3 font-medium text-emerald-500 dark:text-emerald-400">Subtotal</th>
                                    <th class="text-right px-4 py-3 font-medium text-emerald-500 dark:text-emerald-400">IVA Total</th>
                                    <th class="text-right px-4 py-3 font-medium text-emerald-500 dark:text-emerald-400">Total</th>
                                    <th class="text-center px-3 py-3 font-medium text-gray-500 dark:text-gray-400 w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineas as $idx => $linea)
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-3 py-3 text-center text-gray-500 dark:text-gray-400">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-800 dark:text-gray-100">{{ $linea['producto_nombre'] }}</p>
                                            @if ($linea['es_ajuste'] ?? false)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 mt-0.5">AJUSTE</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">{{ $linea['cantidad'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_costo_unit'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_iva_unit'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_con_iva_unit'], 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_base'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_iva'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($linea['valor_con_iva'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <button wire:click="eliminarLinea({{ $idx }})" class="text-rose-500 hover:text-rose-700 transition" title="Eliminar línea">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Resumen totales --}}
                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Resumen Factura</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Subtotal</p>
                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($this->totalFactura['subtotal'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">IVA</p>
                            <p class="text-lg font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($this->totalFactura['total_iva'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
                            <p class="text-xs text-blue-500">Total sin retenciones</p>
                            <p class="text-lg font-bold text-blue-700 dark:text-blue-400">$ {{ number_format($this->totalFactura['subtotal'] + $this->totalFactura['total_iva'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 px-4 py-3">
                            <p class="text-xs text-rose-500">Retenciones</p>
                            <p class="text-lg font-bold text-rose-700 dark:text-rose-400">-$ {{ number_format($this->totalFactura['total_retenciones'], 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
                            <p class="text-xs text-emerald-500">Total</p>
                            <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">$ {{ number_format($this->totalFactura['total'], 2, ',', '.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Botones de acción --}}
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" wire:click="nuevaFactura" class="px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Nueva Factura
                    </button>
                    @if ($estadoFactura === 'borrador')
                        <button type="button" wire:click="grabarFactura" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-5 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-medium transition disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="grabarFactura">{{ $factura_id ? 'Actualizar Factura' : 'Grabar Factura' }}</span>
                            <span wire:loading wire:target="grabarFactura" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Guardando...
                            </span>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
