<?php

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\Movirubro;
use App\Models\Pago;
use App\Models\Pagodetaregistro;
use App\Models\Pagodeterubro;
use App\Models\TramitePago;
use App\Traits\FiltrablePorRegional;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    use FiltrablePorRegional;

    public string $numcontrato = '';
    public ?Contrato $contrato = null;
    public string $contratoError = '';
    public bool $guardando = false;
    public bool $confirmando = false;

    // Datos del pago
    public string $fecha_pago = '';
    public int $siguientePago = 0;
    public int $siguienteTramite = 0;

    // Pago creado (en estado abierto)
    public ?int $pago_id = null;
    public string $pago_numero = '';
    public string $pago_estado = '';

    // Modal facturas
    public bool $modalFacturas = false;
    public array $facturasSeleccionadas = [];
    public array $lineasAgregadas = [];

    // Modal confirmar
    public bool $confirmModalOpen = false;

    public function mount(): void
    {
        $this->fecha_pago = now()->format('Y-m-d');
    }

    public function buscarContrato(): void
    {
        $this->contratoError = '';
        $this->contrato = null;
        $this->siguientePago = 0;
        $this->siguienteTramite = 0;
        $this->lineasAgregadas = [];
        $this->pago_id = null;
        $this->pago_numero = '';
        $this->pago_estado = '';

        if (empty(trim($this->numcontrato))) {
            $this->contratoError = 'Ingrese un número de contrato.';
            return;
        }

        $this->contrato = Contrato::with(['proveedor', 'movirubros.rubro'])
            ->where('numcontrato', trim($this->numcontrato))
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->first();

        if (!$this->contrato) {
            $this->contratoError = 'No se encontró un contrato con ese número.';
            return;
        }

        $this->siguientePago = $this->contrato->cansecu_pagos + 1;
        $this->siguienteTramite = $this->contrato->cansecu_tramite + 1;

        // Verificar pagos pendientes de trámite de pago (cualquier estado excepto anulada)
        $pagosSinTramite = Pago::where('contrato_id', $this->contrato->id)
            ->where('estado', '!=', 'anulada')
            ->whereNull('tramite_pago_id')
            ->get();

        if ($pagosSinTramite->isNotEmpty()) {
            $numeros = $pagosSinTramite->pluck('numero')->implode(', ');
            $this->contratoError = 'El contrato tiene pago(s) pendiente(s) de trámite de pago: ' . $numeros . '. Debe crear el trámite de pago correspondiente antes de crear uno nuevo.';
            $this->contrato = null;
            return;
        }
    }

    public function getFacturasDisponiblesProperty()
    {
        if (!$this->contrato) return collect();

        $idsYaAgregadas = collect($this->lineasAgregadas)->pluck('factura_id')->unique()->toArray();

        $idsEnOtrosPagos = DB::table('detalle_pagos')
            ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
            ->where('pagos.contrato_id', $this->contrato->id)
            ->where('pagos.estado', '!=', 'anulada')
            ->pluck('detalle_pagos.factura_id')
            ->toArray();

        return Factura::where('contrato_id', $this->contrato->id)
            ->where('estado', 'emitida')
            ->whereNotIn('id', array_merge($idsYaAgregadas, $idsEnOtrosPagos))
            ->with('proveedor')
            ->get()
            ->map(function ($factura) {
                $factura->total_sin_retenciones = $factura->subtotal + $factura->total_iva;
                return $factura;
            });
    }

    public function abrirModalFacturas(): void
    {
        $this->facturasSeleccionadas = [];
        $this->modalFacturas = true;
    }

    public function cerrarModalFacturas(): void
    {
        $this->modalFacturas = false;
        $this->facturasSeleccionadas = [];
    }

    public function agregarFacturas(): void
    {
        if (empty($this->facturasSeleccionadas)) {
            session()->flash('error', 'Seleccione al menos una factura.');
            return;
        }

        $facturas = Factura::with(['lineas.itemcontrato.movirubro', 'lineas.producto.uso'])->whereIn('id', $this->facturasSeleccionadas)->get();

        foreach ($facturas as $factura) {
            $agrupadas = $factura->lineas->groupBy(function ($linea) {
                return $linea->producto->uso->nombre_uso ?? 'Sin uso';
            });

            foreach ($agrupadas as $nombreUso => $lineas) {
                $totalSinRetenciones = $lineas->sum(function ($linea) {
                    return $linea->valor_con_iva;
                });

                $primeraLinea = $lineas->first();

                $this->lineasAgregadas[] = [
                    'factura_id' => $factura->id,
                    'numero' => explode('-', $factura->numero)[1] ?? $factura->numero,
                    'fecha' => $factura->fecha->format('d/m/Y'),
                    'uso' => $nombreUso,
                    'valor' => $totalSinRetenciones,
                    'movirubro_id' => $primeraLinea->itemcontrato->movirubro_id ?? null,
                    'uso_id' => $primeraLinea->producto->uso_id ?? null,
                    'rubro_id' => $primeraLinea->producto->rubro_id ?? null,
                ];
            }
        }

        $this->cerrarModalFacturas();
    }

    public function eliminarLineaAgregada(int $index): void
    {
        unset($this->lineasAgregadas[$index]);
        $this->lineasAgregadas = array_values($this->lineasAgregadas);
    }

    public function getValorTotalProperty(): float
    {
        return collect($this->lineasAgregadas)->sum('valor');
    }

    public function getFacturasUnicasProperty(): array
    {
        return collect($this->lineasAgregadas)
            ->groupBy(function ($item) {
                return $item['factura_id'] . '|' . $item['uso'];
            })
            ->map(function ($lineas) {
                return [
                    'factura_id' => $lineas->first()['factura_id'],
                    'numero' => $lineas->first()['numero'],
                    'fecha' => $lineas->first()['fecha'],
                    'uso' => $lineas->first()['uso'],
                    'total' => $lineas->sum('valor'),
                    'movirubro_id' => $lineas->first()['movirubro_id'],
                    'uso_id' => $lineas->first()['uso_id'],
                    'rubro_id' => $lineas->first()['rubro_id'],
                ];
            })
            ->values()
            ->toArray();
    }

    // ------------------------------------------------------------------
    // Snapshot histórico de registros y rubros (post-descuento)
    // ------------------------------------------------------------------

    private function crearSnapshot(Pago $pago): void
    {
        $movirubros = Movirubro::with(['registro', 'rubro'])
            ->where('contrato_id', $pago->contrato_id)
            ->get();

        $registrosGuardados = [];

        foreach ($movirubros as $mov) {
            $registro = $mov->registro;

            if (!in_array($mov->registro_id, $registrosGuardados)) {
                Pagodetaregistro::firstOrCreate(
                    ['pago_id' => $pago->id, 'registro_id' => $mov->registro_id],
                    [
                        'numero_reg' => $registro->numero_reg ?? '',
                        'valor_reg' => $registro->valor_reg ?? 0,
                        'fecha_reg' => $registro->fecha_reg ?? now(),
                        'estado' => $registro->estado ?? true,
                        'newplazoejecucion' => $registro->newplazoejecucion ?? now(),
                        'tiporegistro_id' => $registro->tiporegistro_id ?? 1,
                    ]
                );
                $registrosGuardados[] = $mov->registro_id;
            }

            $saldoActual = Movirubro::where('id', $mov->id)->value('saldo_rubro');

            Pagodeterubro::create([
                'pago_id' => $pago->id,
                'movirubro_id' => $mov->id,
                'registro_id' => $mov->registro_id,
                'rubro_id' => $mov->rubro_id,
                'valor_rubro' => $mov->valor_rubro,
                'saldo_rubro' => $saldoActual,
                'dependencia_afectacion' => $mov->dependencia_afectacion,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Guardar pago (abierto - permite agregar/eliminar facturas)
    // ------------------------------------------------------------------

    public function guardar(): void
    {
        if (!$this->contrato) {
            session()->flash('error', 'Debe buscar un contrato primero.');
            return;
        }

        // Verificar pagos pendientes de trámite de pago (excepto el que se está creando)
        $queryTramite = Pago::where('contrato_id', $this->contrato->id)
            ->where('estado', '!=', 'anulada')
            ->whereNull('tramite_pago_id');

        if ($this->pago_id) {
            $queryTramite->where('id', '!=', $this->pago_id);
        }

        $pagosSinTramite = $queryTramite->first();

        if ($pagosSinTramite) {
            session()->flash('error', 'No se puede guardar. El contrato tiene el pago N° ' . $pagosSinTramite->numero . ' pendiente de trámite de pago.');
            return;
        }

        if (empty($this->lineasAgregadas)) {
            session()->flash('error', 'Debe agregar al menos una factura.');
            return;
        }

        $this->guardando = true;

        try {
            $year = date('Y');
            $numero = str_pad($this->siguientePago, 3, '0', STR_PAD_LEFT) . '-' . $year;

            $pago = Pago::create([
                'numero' => $numero,
                'fecha' => $this->fecha_pago,
                'contrato_id' => $this->contrato->id,
                'cansecu_pagos' => $this->siguientePago,
                'cansecu_infor' => $this->contrato->cansecu_infor + 1,
                'cansecu_tramite' => $this->siguienteTramite,
                'valor_total' => $this->valorTotal,
                'estado' => 'abierto',
                'user_id' => Auth::id(),
            ]);

            foreach ($this->facturasUnicas as $facturaData) {
                $pago->detalles()->create([
                    'factura_id' => $facturaData['factura_id'],
                    'valor_pagado' => $facturaData['total'],
                    'movirubro_id' => $facturaData['movirubro_id'],
                    'uso_id' => $facturaData['uso_id'],
                    'rubro_id' => $facturaData['rubro_id'],
                ]);
            }

            $this->contrato->update(['cansecu_pagos' => $this->siguientePago]);

            $this->pago_id = $pago->id;
            $this->pago_numero = $numero;
            $this->pago_estado = 'abierto';

            session()->flash('message', 'Pago ' . $numero . ' creado. Puede agregar más facturas o confirmar el pago.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al crear el pago: ' . $e->getMessage());
        } finally {
            $this->guardando = false;
        }
    }

    // ------------------------------------------------------------------
    // Confirmar pago (cierra y descuenta saldos)
    // ------------------------------------------------------------------

    public function abrirConfirmar(): void
    {
        if (empty($this->lineasAgregadas)) {
            session()->flash('error', 'Debe agregar al menos una factura.');
            return;
        }
        $this->confirmModalOpen = true;
    }

    public function cerrarConfirmar(): void
    {
        $this->confirmModalOpen = false;
    }

    public function confirmarPago(): void
    {
        if (!$this->pago_id) {
            session()->flash('error', 'No hay pago para confirmar.');
            return;
        }

        $this->confirmando = true;
        $this->confirmModalOpen = false;

        try {
            DB::beginTransaction();

            $pago = Pago::with(['detalles.factura.lineas.itemcontrato.movirubro'])->findOrFail($this->pago_id);

            // Recalcular valor total desde las líneas agregadas
            $nuevoTotal = $this->valorTotal;
            $pago->update([
                'valor_total' => $nuevoTotal,
                'cansecu_infor' => $this->contrato->cansecu_infor + 1,
            ]);

            // Sincronizar detalles: eliminar los viejos y crear los nuevos
            $pago->detalles()->delete();
            foreach ($this->facturasUnicas as $factura) {
                $pago->detalles()->create([
                    'factura_id' => $factura['factura_id'],
                    'valor_pagado' => $factura['total'],
                    'movirubro_id' => $factura['movirubro_id'],
                    'uso_id' => $factura['uso_id'],
                    'rubro_id' => $factura['rubro_id'],
                ]);
            }

            // Recargar detalles
            $pago->load('detalles.factura.lineas.itemcontrato.movirubro');

            // Agrupar deducciones por movirubro_id para validar el total antes de descontar
            $deducciones = [];
            $facturasIds = [];

            $facturasUnicas = $pago->detalles->pluck('factura')->unique('id');
            foreach ($facturasUnicas as $factura) {
                $facturasIds[] = $factura->id;

                foreach ($factura->lineas as $linea) {
                    if ($linea->itemcontrato && $linea->itemcontrato->movirubro) {
                        $movId = $linea->itemcontrato->movirubro_id;

                        if (!isset($deducciones[$movId])) {
                            $deducciones[$movId] = [
                                'movirubro' => $linea->itemcontrato->movirubro,
                                'total' => 0,
                            ];
                        }
                        $deducciones[$movId]['total'] += $linea->valor_con_iva;
                    }
                }
            }

            // Validar y aplicar descuentos
            foreach ($deducciones as $item) {
                $movirubro = $item['movirubro'];
                $saldoActual = Movirubro::where('id', $movirubro->id)->value('saldo_rubro');
                $nuevoSaldo = $saldoActual - $item['total'];

                if ($nuevoSaldo < 0) {
                    DB::rollBack();
                    session()->flash('error', 'Saldo insuficiente en rubro "' . ($movirubro->rubro->nombre_rubro ?? '-') . '". Saldo: $' . number_format($saldoActual, 2, ',', '.') . ', requerido: $' . number_format($item['total'], 2, ',', '.'));
                    return;
                }

                Movirubro::where('id', $movirubro->id)->update(['saldo_rubro' => $nuevoSaldo]);
            }

            $this->crearSnapshot($pago);

            // Cambiar estado de facturas a pagada
            Factura::whereIn('id', $facturasIds)->update(['estado' => 'pagada']);

            // Cerrar pago
            $pago->update([
                'estado' => 'cerrado',
                'fecha_cierre' => now(),
            ]);

            DB::commit();

            $this->pago_estado = 'cerrado';
            $this->lineasAgregadas = [];
            session()->flash('message', 'Pago ' . $this->pago_numero . ' confirmado. Saldos actualizados.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error al confirmar el pago: ' . $e->getMessage());
        } finally {
            $this->confirmando = false;
        }
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Crear Pago</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ingrese el número del contrato para iniciar el pago.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- Buscador centrado --}}
    <div class="flex justify-center mb-8">
        <div class="w-full max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Número de Contrato</label>
            <div class="flex gap-2">
                <input type="text" wire:model.live="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: 010-010-2026"
                    class="form-input flex-1 text-center text-lg" />
                <button type="button" wire:click="buscarContrato" class="btn bg-violet-500 hover:bg-violet-600 text-white">
                    Buscar
                </button>
            </div>
            @if ($contratoError)
                <p class="mt-2 text-sm text-rose-600">{{ $contratoError }}</p>
            @endif
        </div>
    </div>

    {{-- Datos del contrato --}}
    @if ($contrato)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Datos del Contrato</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Número</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Proveedor</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Objeto</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->objetocontrato ?? '-' }}</span>
                </div>
            </div>

            {{-- Consecutivos --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Consecutivo Pagos (Siguiente)</span>
                    <span class="text-lg font-bold text-violet-600 dark:text-violet-400">{{ $siguientePago }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Consecutivo Trámites (Siguiente)</span>
                    <span class="text-lg font-bold text-violet-600 dark:text-violet-400">{{ $siguienteTramite }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Consecutivo Informe (Siguiente)</span>
                    <span class="text-lg font-bold text-violet-600 dark:text-violet-400">{{ $contrato->cansecu_infor + 1 }}</span>
                </div>
            </div>

            {{-- Valor y saldo --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4">
                    <span class="block text-xs text-emerald-600 dark:text-emerald-400">Valor Total Contrato</span>
                    <span class="text-lg font-bold text-emerald-700 dark:text-emerald-300">${{ number_format($contrato->valorTotal, 2, ',', '.') }}</span>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <span class="block text-xs text-blue-600 dark:text-blue-400">Saldo Disponible</span>
                    <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${{ number_format($contrato->saldo, 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- Rubros --}}
            <h3 class="text-md font-bold text-gray-800 dark:text-gray-100 mb-3">Rubros</h3>
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-hidden mb-6">
                <table class="table-auto w-full">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">Rubro</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse ($contrato->movirubros as $movirubro)
                            <tr>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $movirubro->rubro->nombre_rubro ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">${{ number_format($movirubro->valor_rubro, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $movirubro->saldo_rubro > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    ${{ number_format($movirubro->saldo_rubro, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No hay rubros registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Botón agregar facturas --}}
            @if ($pago_estado !== 'cerrado')
                <div class="mb-4">
                    <button type="button" wire:click="abrirModalFacturas" class="btn bg-violet-500 hover:bg-violet-600 text-white">
                        <svg class="w-4 h-4 fill-current mr-1 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
                        Agregar Facturas
                    </button>
                </div>
            @endif

            {{-- Facturas agregadas --}}
            @if (!empty($lineasAgregadas))
                <div class="mb-6">
                    <h3 class="text-md font-bold text-gray-800 dark:text-gray-100 mb-3">Facturas Agregadas</h3>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 overflow-hidden">
                        <table class="table-auto w-full">
                            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                                <tr>
                                    <th class="px-4 py-3 text-left">N° Factura</th>
                                    <th class="px-4 py-3 text-left">Fecha</th>
                                    <th class="px-4 py-3 text-left">Uso</th>
                                    <th class="px-4 py-3 text-right">Valor</th>
                                    @if ($pago_estado !== 'cerrado')
                                        <th class="px-4 py-3 text-right">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                                @foreach ($lineasAgregadas as $index => $linea)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $linea['numero'] }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $linea['fecha'] }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $linea['uso'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($linea['valor'], 2, ',', '.') }}</td>
                                        @if ($pago_estado !== 'cerrado')
                                            <td class="px-4 py-3 text-right">
                                                <button type="button" wire:click="eliminarLineaAgregada({{ $index }})" class="text-rose-500 hover:text-rose-600" title="Eliminar">
                                                    <svg class="w-5 h-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-gray-700/30">
                                    <td colspan="{{ $pago_estado !== 'cerrado' ? 3 : 3 }}" class="px-4 py-3 text-right font-bold text-gray-800 dark:text-gray-100">Total Pago:</td>
                                    <td class="px-4 py-3 text-right font-bold text-lg text-emerald-600 dark:text-emerald-400">${{ number_format($this->valorTotal, 2, ',', '.') }}</td>
                                    @if ($pago_estado !== 'cerrado')
                                        <td></td>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Botones de acción --}}
            <div class="mt-6 flex justify-end space-x-3">
                @if (!$pago_id)
                    <button type="button" wire:click="guardar" wire:loading.attr="disabled"
                        class="btn bg-violet-500 hover:bg-violet-600 text-white disabled:opacity-50"
                        {{ $guardando ? 'disabled' : '' }}>
                        @if ($guardando)
                            <svg class="animate-spin w-4 h-4 mr-1 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Guardando...
                        @else
                            Guardar Pago
                        @endif
                    </button>
                @endif

                @if ($pago_estado !== 'cerrado' && $pago_id)
                    <button type="button" wire:click="abrirConfirmar" wire:loading.attr="disabled"
                        class="btn bg-emerald-500 hover:bg-emerald-600 text-white disabled:opacity-50">
                        <svg class="w-4 h-4 fill-current mr-1 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Confirmar Pago
                    </button>
                @endif

                @if ($pago_estado === 'cerrado')
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Pago Confirmado
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Modal Agregar Facturas --}}
    @if ($modalFacturas)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="cerrarModalFacturas">
            <div class="w-full max-w-3xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[80vh] overflow-y-auto" wire:click.stop>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Agregar Facturas</h2>

                @php
                    $disponibles = $this->facturasDisponibles;
                @endphp

                @if ($disponibles->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No hay facturas emitidas disponibles para este contrato.</p>
                @else
                    <table class="table-auto w-full mb-4">
                        <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                            <tr>
                                <th class="px-4 py-3 text-center w-10"></th>
                                <th class="px-4 py-3 text-left">Número</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                            @foreach ($disponibles as $factura)
                                <tr>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" wire:model.live="facturasSeleccionadas" value="{{ $factura->id }}"
                                            class="rounded border-gray-300 text-violet-500 focus:ring-violet-500" />
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ explode('-', $factura->numero)[1] ?? $factura->numero }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">${{ number_format($factura->total_sin_retenciones, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="cerrarModalFacturas" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="agregarFacturas" class="btn bg-violet-500 hover:bg-violet-600 text-white" {{ count($facturasSeleccionadas) === 0 ? 'disabled' : '' }}>
                        Agregar ({{ count($facturasSeleccionadas) }})
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Confirmar Pago --}}
    @if ($confirmModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="cerrarConfirmar">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[80vh] overflow-y-auto" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-2 text-center">Confirmar Pago</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-2">
                    ¿Estás seguro de confirmar el pago <span class="font-semibold">{{ $pago_numero }}</span>?
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-6">
                    Se descontarán los valores de los rubros del contrato y las facturas cambiarán a estado <span class="font-semibold">pagada</span>.
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" wire:click="cerrarConfirmar" class="btn border border-gray-200 dark:border-gray-700/60 hover:bg-gray-50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="confirmarPago" wire:loading.attr="disabled"
                        class="btn bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-600 disabled:opacity-50"
                        {{ $confirmando ? 'disabled' : '' }}>
                        @if ($confirmando)
                            <svg class="animate-spin w-4 h-4 mr-1 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Confirmando...
                        @else
                            Confirmar
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
