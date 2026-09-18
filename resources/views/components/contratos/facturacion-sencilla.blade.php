<?php

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Itemcontrato;
use App\Models\Movirubro;
use App\Models\Municipio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Uso;
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

    // Selección de rubro/uso
    public ?int $movirubro_id = null;
    public ?int $uso_id = null;
    public string $tipo_adquisicion = 'bien';

    // Valor total
    public float $valor_total = 0;
    public float $porcentaje_iva = 19;

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

    // ------------------------------------------------------------------
    // Usos disponibles (filtrados por rubro del movirubro seleccionado)
    // ------------------------------------------------------------------

    public function getUsosDisponiblesProperty()
    {
        if (!$this->movirubro_id || !$this->contrato) {
            return collect();
        }

        $movirubro = $this->contrato->movirubros->firstWhere('id', $this->movirubro_id);
        if (!$movirubro) {
            return collect();
        }

        return Uso::where('rubro_id', $movirubro->rubro_id)->orderBy('nombre_uso')->get();
    }

    public function getMovirubroSeleccionadoProperty()
    {
        if (!$this->movirubro_id || !$this->contrato) {
            return null;
        }

        return $this->contrato->movirubros->firstWhere('id', $this->movirubro_id);
    }

    // ------------------------------------------------------------------
    // Cálculos de valores
    // ------------------------------------------------------------------

    public function getValorBaseProperty(): float
    {
        if (!$this->contrato || $this->valor_total <= 0) return 0;

        $porcentajeIva = (float) $this->porcentaje_iva;
        if ($porcentajeIva <= 0) return $this->valor_total;

        return round($this->valor_total / (1 + ($porcentajeIva / 100)), 2);
    }

    public function getValorIvaProperty(): float
    {
        return round($this->valor_total - $this->valorBase, 2);
    }

    // ------------------------------------------------------------------
    // Preview de retenciones
    // ------------------------------------------------------------------

    public function getPreviewRetencionesProperty(): array
    {
        try {
            if (!$this->contrato || $this->valor_total <= 0) {
                return ['calculadas' => [], 'pendientes' => []];
            }

            $producto = Producto::where('name', 'Facturación Sencilla')->first();
            if (!$producto) {
                return ['calculadas' => [], 'pendientes' => []];
            }

            $lineaTemp = new FacturaLinea([
                'producto_id'              => $producto->id,
                'tipo_adquisicion'         => $this->tipo_adquisicion,
                'municipio_id'             => $this->municipio_default_id,
                'estampilla_retencion_id'  => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
                'valor_base'               => $this->valorBase,
                'valor_iva'                => $this->valorIva,
                'valor_con_iva'            => $this->valor_total,
                'cantidad'                 => 1,
            ]);

            $lineaTemp->setRelation('producto', $producto);

            $proveedor = $this->contrato->proveedor;
            if ($proveedor) {
                $proveedor->load('regimenTributario.retenciones');
            }

            $servicio = new CalculadoraRetenciones();
            return $servicio->calcular($lineaTemp, $proveedor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en previewRetenciones: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['calculadas' => [], 'pendientes' => []];
        }
    }

    // ------------------------------------------------------------------
    // Totales
    // ------------------------------------------------------------------

    public function getTotalFacturaProperty(): array
    {
        try {
            $subtotal = $this->valorBase;
            $totalIva = $this->valorIva;
            $totalRetenciones = collect($this->previewRetenciones['calculadas'] ?? [])->sum('valor_retenido');

            return [
                'subtotal'          => $subtotal,
                'total_iva'         => $totalIva,
                'total_retenciones' => $totalRetenciones,
                'total'             => $subtotal + $totalIva - $totalRetenciones,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en totalFactura: ' . $e->getMessage());
            return ['subtotal' => 0, 'total_iva' => 0, 'total_retenciones' => 0, 'total' => 0];
        }
    }

    // ------------------------------------------------------------------
    // Saldo por rubro
    // ------------------------------------------------------------------

    public function getSaldoPorRubroProperty(): array
    {
        try {
            if (!$this->contrato || !$this->movirubro_id || $this->valor_total <= 0) {
                return [];
            }

            $movirubro = $this->contrato->movirubros->firstWhere('id', $this->movirubro_id);
            if (!$movirubro) return [];

            $consumo = $this->valor_total;

            $saldoDisponible = (float) $movirubro->saldo_rubro;

            $facturasExistentes = FacturaLinea::whereHas('factura', function ($q) use ($movirubro) {
                $q->where('contrato_id', $this->contrato->id)
                  ->whereIn('estado', ['borrador', 'emitida']);
                if ($this->factura_id) {
                    $q->where('id', '!=', $this->factura_id);
                }
            })->whereHas('itemcontrato', function ($q) use ($movirubro) {
                $q->where('movirubro_id', $movirubro->id);
            })->sum('valor_con_iva');

            $restante = $saldoDisponible - $facturasExistentes - $consumo;

            return [
                [
                    'movirubro_id'     => $movirubro->id,
                    'codigo_rubro'     => $movirubro->rubro->codigo_rubro ?? '—',
                    'nombre_rubro'     => $movirubro->rubro->nombre_rubro ?? '—',
                    'saldo_disponible' => $saldoDisponible,
                    'otras_facturas'   => $facturasExistentes,
                    'consumo_factura'  => $consumo,
                    'restante'         => $restante,
                ],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en saldoPorRubro: ' . $e->getMessage());
            return [];
        }
    }

    public function getHayExcesoSaldoProperty(): bool
    {
        return collect($this->saldoPorRubro)->contains(fn($r) => $r['restante'] < -0.01);
    }

    // ------------------------------------------------------------------
    // Buscar contrato
    // ------------------------------------------------------------------

    public function buscarContrato(): void
    {
        $this->contratoError = '';
        $this->contrato = null;
        $this->movirubro_id = null;
        $this->uso_id = null;
        $this->valor_total = 0;
        $this->porcentaje_iva = 19;
        $this->tipo_adquisicion = 'bien';
        $this->numero_factura = '';
        $this->fecha_factura = '';
        $this->numero_migo = '';
        $this->fecha_migo = '';
        $this->municipio_default_id = null;
        $this->estampilla_default_id = '';
        $this->dependencia_id = null;
        $this->nota_credito = '';
        $this->nota_credito_valor = null;
        $this->factura_id = null;
        $this->estadoFactura = 'borrador';

        if (empty(trim($this->numcontrato))) {
            $this->contratoError = 'Ingrese un número de contrato.';
            return;
        }

        $this->contrato = Contrato::with(['proveedor.regimenTributario', 'movirubros.rubro'])
            ->where('numcontrato', trim($this->numcontrato))
            ->first();

        if (!$this->contrato) {
            $this->contratoError = 'No se encontró un contrato con ese número.';
            return;
        }

        $this->fecha_factura = now()->format('Y-m-d');

        $user = Auth::user();
        $this->municipio_default_id = $user->regional?->municipio_id ?? null;
    }

    // ------------------------------------------------------------------
    // Cuando cambia el movirubro seleccionado
    // ------------------------------------------------------------------

    public function updatedMovirubroId(): void
    {
        $this->uso_id = null;
    }

    // ------------------------------------------------------------------
    // Guardar factura sencilla
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

        if (empty($this->dependencia_id)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar una Dependencia / Comedor.');
            $this->guardando = false;
            return;
        }

        if (!$this->movirubro_id) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar un rubro (movirubro).');
            $this->guardando = false;
            return;
        }

        if (!$this->uso_id) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe seleccionar un uso.');
            $this->guardando = false;
            return;
        }

        if ($this->tipo_adquisicion === 'servicio' && empty($this->municipio_default_id)) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Los servicios deben tener un municipio seleccionado para Reteica.');
            $this->guardando = false;
            return;
        }

        if ($this->valor_total <= 0) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'El valor total debe ser mayor a cero.');
            $this->guardando = false;
            return;
        }

        // Validar saldo
        if ($this->hayExcesoSaldo) {
            $item = $this->saldoPorRubro[0] ?? null;
            $nombreRubro = $item['nombre_rubro'] ?? 'Sin nombre';
            $codigoRubro = $item['codigo_rubro'] ?? '';
            $saldoRestante = $item['restante'] ?? 0;
            $this->dispatch('alerta', tipo: 'error', mensaje: 'El rubro "' . $codigoRubro . ' - ' . $nombreRubro . '" no tiene saldo suficiente. Saldo disponible: $' . number_format($item['saldo_disponible'] - ($item['otras_facturas'] ?? 0), 2, ',', '.') . ', total de la factura: $' . number_format($this->valor_total, 2, ',', '.'));
            $this->guardando = false;
            return;
        }

        $servicio = new CalculadoraRetenciones();
        $year = date('Y', strtotime($this->fecha_factura));
        $numeroInterno = $this->contrato->proveedor_id . '-' . trim($this->numero_factura) . '-' . $year;

        if ($this->factura_id) {
            // Editar factura existente
            $factura = Factura::find($this->factura_id);
            if (!$factura || $factura->estado !== 'borrador') {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'Solo se pueden editar facturas en estado borrador.');
                $this->guardando = false;
                return;
            }

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
                'numero'              => $numeroInterno,
                'fecha'               => $this->fecha_factura,
                'numero_migo'         => $this->numero_migo ?: null,
                'fecha_migo'          => $this->fecha_migo ?: null,
                'municipio_id'        => $this->municipio_default_id,
                'dependencia_id'      => $this->dependencia_id,
                'nota_credito'        => $this->nota_credito ?: null,
                'nota_credito_valor'  => $this->nota_credito_valor,
                'tipo'                => 'sencilla',
                'movirubro_id'        => $this->movirubro_id,
            ]);
        } else {
            // Crear factura nueva
            $existe = Factura::where('numero', $numeroInterno)->exists();
            if ($existe) {
                $this->dispatch('alerta', tipo: 'error', mensaje: 'Ya existe una factura con ese número para este proveedor.');
                $this->guardando = false;
                return;
            }

            $factura = Factura::create([
                'proveedor_id'        => $this->contrato->proveedor_id,
                'contrato_id'         => $this->contrato->id,
                'movirubro_id'        => $this->movirubro_id,
                'numero'              => $numeroInterno,
                'numero_migo'         => $this->numero_migo ?: null,
                'fecha_migo'          => $this->fecha_migo ?: null,
                'fecha'               => $this->fecha_factura,
                'municipio_id'        => $this->municipio_default_id,
                'dependencia_id'      => $this->dependencia_id,
                'nota_credito'        => $this->nota_credito ?: null,
                'nota_credito_valor'  => $this->nota_credito_valor,
                'estado'              => 'borrador',
                'tipo'                => 'sencilla',
            ]);

            $this->factura_id = $factura->id;
        }

        // Obtener producto genérico
        $producto = Producto::where('name', 'Facturación Sencilla')->first();
        if (!$producto) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'No existe el producto genérico "Facturación Sencilla". Ejecute el seeder.');
            $this->guardando = false;
            return;
        }

        // Crear la única línea sintética
        $facturaLinea = FacturaLinea::create([
            'factura_id'              => $factura->id,
            'producto_id'             => $producto->id,
            'itemcontrato_id'         => null,
            'movirubro_id'            => $this->movirubro_id,
            'uso_id'                  => $this->uso_id,
            'tipo_adquisicion'        => $this->tipo_adquisicion,
            'municipio_id'            => $this->municipio_default_id,
            'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
            'valor_base'              => $this->valorBase,
            'valor_iva'               => $this->valorIva,
            'valor_con_iva'           => $this->valor_total,
            'cantidad'                => 1,
            'es_ajuste'               => false,
        ]);

        // Calcular y persistir retenciones
        $servicio->calcularYPersistir($facturaLinea, $this->contrato->proveedor);

        // Guardar totales
        $totales = $this->totalFactura;
        $factura->update([
            'subtotal'          => $totales['subtotal'],
            'total_iva'         => $totales['total_iva'],
            'total_retenciones' => $totales['total_retenciones'],
            'total'             => $totales['total'],
        ]);

        $this->guardando = false;
        $this->estadoFactura = 'emitida';

        $this->dispatch('alerta', tipo: 'success', mensaje: 'Factura sencilla guardada correctamente.');
    }

    // ------------------------------------------------------------------
    // Nueva factura
    // ------------------------------------------------------------------

    public function nuevaFactura(): void
    {
        $this->reset(['factura_id', 'numero_factura', 'fecha_factura', 'numero_migo', 'fecha_migo', 'municipio_default_id', 'estampilla_default_id', 'dependencia_id', 'nota_credito', 'nota_credito_valor', 'movirubro_id', 'uso_id', 'tipo_adquisicion', 'valor_total', 'porcentaje_iva', 'estadoFactura']);

        $this->fecha_factura = now()->format('Y-m-d');
        $this->porcentaje_iva = 19;
        $user = Auth::user();
        $this->municipio_default_id = $user->regional?->municipio_id ?? null;
    }

    // ------------------------------------------------------------------
    // Cargar factura existente (para edición)
    // ------------------------------------------------------------------

    public function cargarFactura(int $id): void
    {
        $factura = Factura::with(['lineas.retenciones.retencion', 'lineas.producto', 'proveedor.regimenTributario.retenciones', 'contrato.movirubros.rubro'])->find($id);

        if (!$factura) {
            $this->contratoError = 'Factura no encontrada.';
            return;
        }

        $this->factura_id = $factura->id;
        $this->contrato = $factura->contrato;
        $this->numcontrato = $factura->contrato->numcontrato;
        $this->numero_factura = explode('-', $factura->numero)[1] ?? $factura->numero;
        $this->fecha_factura = $factura->fecha->format('Y-m-d');
        $this->numero_migo = $factura->numero_migo ?? '';
        $this->fecha_migo = $factura->fecha_migo ? $factura->fecha_migo->format('Y-m-d') : '';
        $this->municipio_default_id = $factura->municipio_id;
        $this->estampilla_default_id = $factura->lineas->first()?->estampilla_retencion_id ? (string) $factura->lineas->first()->estampilla_retencion_id : '';
        $this->dependencia_id = $factura->dependencia_id;
        $this->nota_credito = $factura->nota_credito ?? '';
        $this->nota_credito_valor = $factura->nota_credito_valor;
        $this->estadoFactura = $factura->estado;
        $this->tipo_adquisicion = $factura->lineas->first()?->tipo_adquisicion ?? 'servicio';
        $this->valor_total = $factura->lineas->first()?->valor_con_iva ?? 0;

        // Calcular % IVA desde la línea existente
        $lineaFirst = $factura->lineas->first();
        if ($lineaFirst && $lineaFirst->valor_base > 0 && $lineaFirst->valor_iva > 0) {
            $this->porcentaje_iva = round(($lineaFirst->valor_iva / $lineaFirst->valor_base) * 100, 2);
        } else {
            $this->porcentaje_iva = 19;
        }

        // Cargar movirubro desde la línea o desde la factura
        $linea = $factura->lineas->first();
        if ($linea) {
            if ($linea->movirubro_id) {
                $this->movirubro_id = $linea->movirubro_id;
            } elseif ($linea->itemcontrato_id) {
                $itemcontrato = Itemcontrato::find($linea->itemcontrato_id);
                if ($itemcontrato) {
                    $this->movirubro_id = $itemcontrato->movirubro_id;
                }
            }
        }
        // Fallback: usar movirubro_id de la factura
        if (!$this->movirubro_id && $factura->movirubro_id) {
            $this->movirubro_id = $factura->movirubro_id;
        }

        // Cargar uso desde la línea (guardado) o desde el producto
        if ($linea && $linea->uso_id) {
            $this->uso_id = $linea->uso_id;
        } elseif ($linea && $linea->producto) {
            $this->uso_id = $linea->producto->uso_id;
        }

        // Reload movirubros with rubro
        $this->contrato->load(['movirubros.rubro', 'proveedor.regimenTributario.retenciones']);
    }
};
?>

<div>
    <div class="flex items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Facturación Sencilla</h1>
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
    @if (!$contrato)
        <div class="flex justify-center">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 shadow-md rounded-lg p-8">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-6 text-center">Facturación Sencilla - Buscar Contrato</h2>
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
                                <th class="text-left px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Código</th>
                                <th class="text-left px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Valor</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contrato->movirubros as $movirubro)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $movirubro->rubro->codigo_rubro ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $movirubro->rubro->nombre_rubro ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right text-gray-800 dark:text-gray-100">$ {{ number_format($movirubro->valor_rubro, 2, ',', '.') }}</td>
                                    <td class="px-6 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">$ {{ number_format($movirubro->saldo_rubro, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">No hay rubros registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Datos de la factura --}}
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dependencia / Comedor *</label>
                        <select wire:model="dependencia_id" class="form-select w-full">
                            <option value="">Ninguna</option>
                            @foreach ($this->dependencias as $dep)
                                <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° MIGO</label>
                        <input type="text" wire:model="numero_migo" class="form-input w-full" placeholder="Ej: 001" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha MIGO</label>
                        <input type="date" wire:model="fecha_migo" class="form-input w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                        <select wire:model.live="municipio_default_id" class="form-select w-full">
                            <option value="">Ninguno</option>
                            @foreach ($this->municipios as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }} ({{ $m->departamento }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla</label>
                        <select wire:model.live="estampilla_default_id" class="form-select w-full">
                            <option value="">Ninguna</option>
                            @foreach ($this->estampillas as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
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

            {{-- Detalle de la Factura Sencilla --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Detalle de la Factura Sencilla</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rubro (Movirubro) *</label>
                        <select wire:model.live="movirubro_id" class="form-select w-full">
                            <option value="">Seleccionar rubro...</option>
                            @foreach ($contrato->movirubros as $mov)
                                <option value="{{ $mov->id }}">{{ $mov->rubro->codigo_rubro ?? '—' }} - {{ $mov->rubro->nombre_rubro ?? '—' }} | Saldo: ${{ number_format($mov->saldo_rubro, 2, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Uso *</label>
                        <select wire:model="uso_id" class="form-select w-full">
                            <option value="">Seleccionar uso...</option>
                            @foreach ($this->usosDisponibles as $uso)
                                <option value="{{ $uso->id }}">{{ $uso->nombre_uso }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo *</label>
                        <div class="flex items-center gap-4 h-[38px]">
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" wire:model="tipo_adquisicion" value="servicio" class="text-violet-600 focus:ring-violet-500" />
                                Servicio
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" wire:model="tipo_adquisicion" value="bien" class="text-violet-600 focus:ring-violet-500" />
                                Bien
                            </label>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Total (con IVA) *</label>
                        <input type="number" wire:model.live="valor_total" min="0" step="0.01" class="form-input w-full" placeholder="Ej: 500000" />
                        <p class="text-xs text-gray-500 mt-1">Debug: valor_total = {{ $valor_total }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">% IVA</label>
                        <input type="number" wire:model.live="porcentaje_iva" min="0" max="100" step="0.01" class="form-input w-full" />
                        <p class="text-xs text-gray-500 mt-1">Debug: porcentaje_iva = {{ $porcentaje_iva }}</p>
                    </div>
                </div>
            </div>

            {{-- Preview de retenciones --}}
            @php
                $porcentajeIvaCalc = (float) $porcentaje_iva;
                $valorBaseCalc = ($porcentajeIvaCalc > 0 && $valor_total > 0) ? round($valor_total / (1 + ($porcentajeIvaCalc / 100)), 2) : $valor_total;
                $valorIvaCalc = round($valor_total - $valorBaseCalc, 2);
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Vista Previa de Retenciones</h3>

                {{-- Base, IVA, Total --}}
                <div class="grid grid-cols-3 gap-3 text-sm mb-4">
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2">
                        <span class="text-xs text-gray-500">Base</span>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($valorBaseCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-3 py-2">
                        <span class="text-xs text-gray-500">IVA</span>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($valorIvaCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 px-3 py-2">
                        <span class="text-xs text-violet-500">Total Línea</span>
                        <p class="font-bold text-violet-700 dark:text-violet-400">$ {{ number_format($valor_total, 2, ',', '.') }}</p>
                    </div>
                </div>

                {{-- Retenciones calculadas --}}
                @if (count($this->previewRetenciones['calculadas'] ?? []) > 0)
                    <div class="mb-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Retenciones Aplicadas:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->previewRetenciones['calculadas'] as $ret)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                    {{ $ret['retencion']->name }}: {{ $ret['porcentaje'] }}% &rarr; ${{ number_format($ret['valor_retenido'], 2, ',', '.') }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Retenciones pendientes --}}
                @if (count($this->previewRetenciones['pendientes'] ?? []) > 0)
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Retenciones Pendientes (no aplica):</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->previewRetenciones['pendientes'] as $pend)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ $pend->name ?? $pend['name'] ?? 'Retención' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (count($this->previewRetenciones['calculadas'] ?? []) === 0 && count($this->previewRetenciones['pendientes'] ?? []) === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay retenciones para esta configuración.</p>
                @endif
            </div>

            {{-- Consumo por Rubro --}}
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden mb-6">
                <div class="p-6 pb-0">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Consumo por Rubro</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Saldo que consume esta factura por cada rubro</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Rubro</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Saldo Disp.</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Otras Facturas</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Esta Factura</th>
                                <th class="text-right px-6 py-3 font-medium text-gray-500 dark:text-gray-400">Restante</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->saldoPorRubro as $item)
                                @php
                                    $esNegativo = $item['restante'] < -0.01;
                                    $esBajo = !$esNegativo && $item['saldo_disponible'] > 0 && $item['restante'] < ($item['saldo_disponible'] * 0.25);
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="px-6 py-3 font-medium text-gray-800 dark:text-gray-100">
                                        {{ $item['codigo_rubro'] }} - {{ $item['nombre_rubro'] }}
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-600 dark:text-gray-300">
                                        $ {{ number_format($item['saldo_disponible'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-600 dark:text-gray-300">
                                        @if ($item['otras_facturas'] > 0)
                                            - $ {{ number_format($item['otras_facturas'], 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right font-bold text-blue-700 dark:text-blue-300">
                                        $ {{ number_format($item['consumo_factura'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-3 text-right font-semibold
                                        {{ $esNegativo ? 'text-rose-600 dark:text-rose-400' : ($esBajo ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">
                                        $ {{ number_format($item['restante'], 2, ',', '.') }}
                                        @if ($esNegativo)
                                            <svg class="inline w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        @elseif ($esBajo)
                                            <svg class="inline w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @php
                    $rubrosConExceso = collect($this->saldoPorRubro)->filter(fn($r) => $r['restante'] < -0.01);
                @endphp
                @if ($rubrosConExceso->isNotEmpty())
                    <div class="px-6 py-3 bg-rose-50 dark:bg-rose-900/20 border-t border-rose-200 dark:border-rose-700/50">
                        @foreach ($rubrosConExceso as $rubro)
                            <p class="text-sm text-rose-600 dark:text-rose-400">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                El rubro "{{ $rubro['codigo_rubro'] }} - {{ $rubro['nombre_rubro'] }}" excede el saldo por $ {{ number_format(abs($rubro['restante']), 2, ',', '.') }}
                            </p>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Resumen totales --}}
            @php
                $totalRetencionesCalc = collect($this->previewRetenciones['calculadas'] ?? [])->sum('valor_retenido');
                $totalSinRetCalc = $valorBaseCalc + $valorIvaCalc;
                $totalFinalCalc = $totalSinRetCalc - $totalRetencionesCalc;
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Resumen Factura</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Subtotal</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($valorBaseCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">IVA</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($valorIvaCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
                        <p class="text-xs text-blue-500">Total sin retenciones</p>
                        <p class="text-lg font-bold text-blue-700 dark:text-blue-400">$ {{ number_format($totalSinRetCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 px-4 py-3">
                        <p class="text-xs text-rose-500">Retenciones</p>
                        <p class="text-lg font-bold text-rose-700 dark:text-rose-400">-$ {{ number_format($totalRetencionesCalc, 2, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
                        <p class="text-xs text-emerald-500">Total</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">$ {{ number_format($totalFinalCalc, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Botones de acción --}}
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" wire:click="nuevaFactura" class="px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Nueva Factura
                </button>
                @if ($estadoFactura === 'borrador')
                    <button type="button" wire:click="grabarFactura" wire:loading.attr="disabled" wire:loading.class="opacity-50" {{ $this->hayExcesoSaldo ? 'disabled' : '' }} class="px-5 py-2.5 rounded-lg {{ $this->hayExcesoSaldo ? 'bg-gray-400 cursor-not-allowed' : 'bg-violet-600 hover:bg-violet-700' }} text-white font-medium transition disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="grabarFactura">{{ $factura_id ? 'Actualizar Factura' : 'Grabar Factura' }}</span>
                        <span wire:loading wire:target="grabarFactura" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Guardando...
                        </span>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
