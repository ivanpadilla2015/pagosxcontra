<?php

use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Itemcontrato;
use App\Models\Municipio;
use App\Services\CalculadoraRetenciones;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $facturaId = null;
    public ?Factura $factura = null;
    public array $lineas = [];
    public array $retencionesPorLinea = [];
    public array $pendientesPorLinea = [];
    public ?int $municipio_default_id = null;
    public string $estampilla_default_id = '';
    public string $numero_migo = '';
    public string $fecha_migo = '';

    #[Livewire\Attributes\Computed]
    public function municipios()
    {
        return Municipio::orderBy('nombre')->get();
    }

    #[Livewire\Attributes\Computed]
    public function estampillas()
    {
        return \App\Models\Retencion::where('tipo', 'territorial')
            ->whereHas('estampillaTarifas')
            ->orderBy('name')
            ->get();
    }

    #[Livewire\Attributes\Computed]
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

    public function mount(int $id): void
    {
        $this->facturaId = $id;
        $this->factura = Factura::with(['proveedor', 'contrato', 'lineas.itemcontrato.producto', 'lineas.municipio', 'lineas.retenciones.retencion'])->findOrFail($id);

        if ($this->factura->estado !== 'borrador') {
            session()->flash('error', 'Solo se pueden editar facturas en estado borrador.');
            return;
        }

        $this->municipio_default_id = $this->factura->municipio_id;
        $this->numero_migo = $this->factura->numero_migo ?? '';
        $this->fecha_migo = $this->factura->fecha_migo ? $this->factura->fecha_migo->format('Y-m-d') : '';

        $primeraEstampilla = $this->factura->lineas->firstWhere('estampilla_retencion_id')?->estampilla_retencion_id;
        $this->estampilla_default_id = $primeraEstampilla ? (string) $primeraEstampilla : '';

        foreach ($this->factura->lineas as $linea) {
            $idx = count($this->lineas);
            $esAjuste = $linea->es_ajuste ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores guardados
                $this->lineas[$idx] = [
                    'factura_linea_id' => $linea->id,
                    'itemcontrato_id' => $linea->itemcontrato_id,
                    'producto_nombre' => $linea->itemcontrato->producto->name ?? '-',
                    'valor_costo_unit' => $linea->valor_base,
                    'iva_unit' => $linea->porcentaje_iva ?? 0,
                    'valor_iva_unit' => $linea->valor_iva,
                    'valor_con_iva_unit' => $linea->valor_con_iva,
                    'cantidad' => $linea->cantidad,
                    'tipo_adquisicion' => $linea->tipo_adquisicion,
                    'municipio_id' => $linea->municipio_id,
                    'estampilla_retencion_id' => $linea->estampilla_retencion_id,
                    'valor_base' => $linea->valor_base,
                    'valor_iva' => $linea->valor_iva,
                    'valor_con_iva' => $linea->valor_con_iva,
                    'es_ajuste' => true,
                    'porcentaje_iva' => $linea->porcentaje_iva,
                ];
            } else {
                // Línea normal: valores del itemcontrato
                $this->lineas[$idx] = [
                    'factura_linea_id' => $linea->id,
                    'itemcontrato_id' => $linea->itemcontrato_id,
                    'producto_nombre' => $linea->itemcontrato->producto->name ?? '-',
                    'valor_costo_unit' => $linea->itemcontrato->valor_costo ?? 0,
                    'iva_unit' => $linea->itemcontrato->iva ?? 0,
                    'valor_iva_unit' => $linea->itemcontrato->valor_iva ?? 0,
                    'valor_con_iva_unit' => $linea->itemcontrato->valor_con_iva ?? 0,
                    'cantidad' => $linea->cantidad,
                    'tipo_adquisicion' => $linea->tipo_adquisicion,
                    'municipio_id' => $linea->municipio_id,
                    'estampilla_retencion_id' => $linea->estampilla_retencion_id,
                    'valor_base' => $linea->valor_base,
                    'valor_iva' => $linea->valor_iva,
                    'valor_con_iva' => $linea->valor_con_iva,
                ];
            }

            $this->retencionesPorLinea[$idx] = $linea->retenciones->map(fn ($r) => [
                'retencion' => $r->retencion,
                'porcentaje' => $r->porcentaje_aplicado,
                'base_calculo' => $r->base_calculo,
                'valor_retenido' => $r->valor_retenido,
            ])->toArray();
        }
    }

    public function updatedLineasCantidad($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];

        // Saltar recálculo para líneas de ajuste (valores fijos)
        if ($this->lineas[$idx]['es_ajuste'] ?? false) {
            $this->recalcularRetencionesLinea($idx);
            return;
        }

        $this->lineas[$idx]['valor_base'] = round($this->lineas[$idx]['valor_costo_unit'] * max(1, $value), 2);
        $this->lineas[$idx]['valor_iva'] = round($this->lineas[$idx]['valor_iva_unit'] * max(1, $value), 2);
        $this->lineas[$idx]['valor_con_iva'] = round($this->lineas[$idx]['valor_con_iva_unit'] * max(1, $value), 2);
        $this->recalcularRetencionesLinea($idx);
    }

    public function updatedLineasMunicipioId($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];
        $this->lineas[$idx]['municipio_id'] = $value;
        $this->recalcularRetencionesLinea($idx);
    }

    public function updatedMunicipioDefaultId(): void
    {
        $val = $this->municipio_default_id !== '' ? (int) $this->municipio_default_id : null;

        foreach ($this->lineas as $idx => $linea) {
            $this->lineas[$idx]['municipio_id'] = $val;
            $this->recalcularRetencionesLinea($idx);
        }
    }

    public function updatedEstampillaDefaultId(): void
    {
        $val = $this->estampilla_default_id !== '' ? (int) $this->estampilla_default_id : null;

        foreach ($this->lineas as $idx => $linea) {
            $this->lineas[$idx]['estampilla_retencion_id'] = $val;
            $this->recalcularRetencionesLinea($idx);
        }
    }

    public function updatedLineasEstampillaRetencionId($value, $key): void
    {
        $parts = explode('.', $key);
        $idx = (int) $parts[0];
        $this->recalcularRetencionesLinea($idx);
    }

    public function recalcularRetencionesLinea(int $idx): void
    {
        if (!isset($this->lineas[$idx])) return;

        $linea = $this->lineas[$idx];

        // Para ajustes: usar producto_id directamente
        $productoId = $linea['es_ajuste'] ?? false
            ? ($this->factura->contrato->itemcontratos->firstWhere('producto_id', $linea['itemcontrato_id'] ?? null)?->producto_id ?? $linea['itemcontrato_id'] ?? null)
            : Itemcontrato::find($linea['itemcontrato_id'])?->producto_id;

        $facturaLinea = new FacturaLinea([
            'factura_id' => $this->facturaId,
            'itemcontrato_id' => $linea['itemcontrato_id'] ?? null,
            'producto_id' => $productoId,
            'tipo_adquisicion' => $linea['tipo_adquisicion'],
            'municipio_id' => $linea['municipio_id'],
            'estampilla_retencion_id' => $linea['estampilla_retencion_id'] ?? null,
            'valor_base' => $linea['valor_base'] ?? 0,
            'valor_iva' => $linea['valor_iva'] ?? 0,
            'valor_con_iva' => $linea['valor_con_iva'] ?? 0,
            'cantidad' => $linea['cantidad'] ?? 1,
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

    public function save(): void
    {
        if (!$this->factura || $this->factura->estado !== 'borrador') {
            session()->flash('error', 'No se puede guardar esta factura.');
            return;
        }

        $servicio = new CalculadoraRetenciones();

        foreach ($this->lineas as $idx => $linea) {
            $facturaLinea = FacturaLinea::find($linea['factura_linea_id']);

            if (!$facturaLinea) continue;

            $esAjuste = $linea['es_ajuste'] ?? false;

            if ($esAjuste) {
                // Línea de ajuste: usar valores personalizados
                $facturaLinea->update([
                    'cantidad' => 1,
                    'municipio_id' => $linea['municipio_id'],
                    'estampilla_retencion_id' => $linea['estampilla_retencion_id'] ?? null,
                    'valor_base' => $linea['valor_base'],
                    'valor_iva' => $linea['valor_iva'],
                    'valor_con_iva' => $linea['valor_con_iva'],
                    'es_ajuste' => true,
                    'porcentaje_iva' => $linea['porcentaje_iva'] ?? null,
                ]);
            } else {
                // Línea normal: valores del itemcontrato
                $facturaLinea->update([
                    'cantidad' => $linea['cantidad'],
                    'municipio_id' => $linea['municipio_id'],
                    'estampilla_retencion_id' => $linea['estampilla_retencion_id'] ?? null,
                    'valor_base' => $linea['valor_base'],
                    'valor_iva' => $linea['valor_iva'],
                    'valor_con_iva' => $linea['valor_base'] + $linea['valor_iva'],
                ]);
            }

            $servicio->calcularYPersistir($facturaLinea);
        }

        $this->factura->update([
            'municipio_id' => $this->municipio_default_id,
            'numero_migo' => $this->numero_migo ?: null,
            'fecha_migo' => $this->fecha_migo ?: null,
            'subtotal' => $this->totalFactura['subtotal'],
            'total_iva' => $this->totalFactura['total_iva'],
            'total_retenciones' => $this->totalFactura['total_retenciones'],
            'total' => $this->totalFactura['total'],
        ]);

        session()->flash('message', 'Factura actualizada correctamente.');
    }

    #[Livewire\Attributes\Computed]
    public function itemcontratosDisponibles()
    {
        $idsYaEnFactura = collect($this->lineas)->pluck('itemcontrato_id')->toArray();
        return $this->factura->contrato->itemcontratos()
            ->with('producto')
            ->whereNotIn('id', $idsYaEnFactura)
            ->get();
    }

    public function agregarLinea(int $itemcontratoId): void
    {
        $item = Itemcontrato::with('producto')->find($itemcontratoId);
        if (!$item) return;

        // Servicio sin municipio asignado → advertencia
        if (($item->producto->tipo ?? 'bien') === 'servicio' && !$item->producto->municipio_id) {
            session()->flash('warning', 'El servicio "' . $item->producto->name . '" no tiene municipio asignado. Seleccione un municipio en la línea para calcular Reteica.');
        }

        $cantidad = 1;

        $municipioLinea = ($item->producto->tipo ?? 'bien') === 'servicio'
            ? $item->producto->municipio_id
            : $this->municipio_default_id;

        $facturaLinea = FacturaLinea::create([
            'factura_id' => $this->facturaId,
            'itemcontrato_id' => $item->id,
            'producto_id' => $item->producto_id,
            'tipo_adquisicion' => $item->producto->tipo ?? 'bien',
            'municipio_id' => $municipioLinea,
            'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
            'valor_base' => $item->valor_costo * $cantidad,
            'valor_iva' => $item->valor_iva * $cantidad,
            'valor_con_iva' => $item->valor_con_iva * $cantidad,
            'cantidad' => $cantidad,
        ]);

        $idx = count($this->lineas);
        $this->lineas[$idx] = [
            'factura_linea_id' => $facturaLinea->id,
            'itemcontrato_id' => $item->id,
            'producto_nombre' => $item->producto->name ?? '-',
            'valor_costo_unit' => $item->valor_costo,
            'iva_unit' => $item->iva,
            'valor_iva_unit' => $item->valor_iva,
            'valor_con_iva_unit' => $item->valor_con_iva,
            'cantidad' => $cantidad,
            'tipo_adquisicion' => $item->producto->tipo ?? 'bien',
            'municipio_id' => $municipioLinea,
            'estampilla_retencion_id' => !empty($this->estampilla_default_id) ? (int) $this->estampilla_default_id : null,
            'valor_base' => $item->valor_costo,
            'valor_iva' => $item->valor_iva,
            'valor_con_iva' => $item->valor_con_iva,
        ];

        $servicio = new CalculadoraRetenciones();
        $resultado = $servicio->calcularYPersistir($facturaLinea);
        $this->retencionesPorLinea[$idx] = $resultado['calculadas'];
        $this->pendientesPorLinea[$idx] = $resultado['pendientes'];
    }

    public function eliminarLinea(int $indice): void
    {
        if (!isset($this->lineas[$indice])) return;

        $facturaLinea = FacturaLinea::find($this->lineas[$indice]['factura_linea_id']);
        if ($facturaLinea) {
            $facturaLinea->retenciones()->delete();
            $facturaLinea->delete();
        }

        array_splice($this->lineas, $indice, 1);
        array_splice($this->retencionesPorLinea, $indice, 1);
        array_splice($this->pendientesPorLinea, $indice, 1);
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('facturas') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Editar Factura</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ explode('-', $factura->numero ?? '')[1] ?? $factura->numero ?? '' }} — {{ $factura->proveedor->nombre ?? '' }}</p>
            </div>
        </div>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-sm border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-sm border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ session('warning') }}</div>
    @endif

    @if ($factura && $factura->estado === 'borrador')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Datos de la Factura</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número</label>
                    <input type="text" value="{{ explode('-', $factura->numero)[1] ?? $factura->numero }}" class="form-input w-full bg-gray-50 dark:bg-gray-700" readonly />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                    <input type="text" value="{{ $factura->fecha->format('d/m/Y') }}" class="form-input w-full bg-gray-50 dark:bg-gray-700" readonly />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                    <select wire:model.live="municipio_default_id" class="form-input w-full">
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
                    <input type="text" wire:model="numero_migo" class="form-input w-full" placeholder="Ej: 001" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha MIGO</label>
                    <input type="date" wire:model="fecha_migo" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estampilla</label>
                    <select wire:model.live="estampilla_default_id" class="form-input w-full">
                        <option value="">Ninguna</option>
                        @foreach ($this->estampillas as $e)
                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if (count($this->itemcontratosDisponibles) > 0)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Agregar Productos del Contrato</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left">Producto</th>
                                <th class="px-4 py-3 text-right">Valor Unit.</th>
                                <th class="px-4 py-3 text-center">IVA %</th>
                                <th class="px-4 py-3 text-right">Valor c/IVA</th>
                                <th class="px-4 py-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->itemcontratosDisponibles as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->producto->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item->valor_costo, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $item->iva }}%</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($item->valor_con_iva, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="agregarLinea({{ $item->id }})" class="px-3 py-1 text-xs font-medium rounded-lg bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-400 transition">
                                            Agregar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700/60 p-4 mb-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center">Todos los productos del contrato ya están en esta factura.</p>
            </div>
        @endif

        @if (count($this->lineas) > 0)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Líneas de la Factura</h2>
                    <button wire:click="save" wire:confirm="¿Guardar cambios?" class="btn bg-violet-600 hover:bg-violet-700 text-white border border-violet-600">Guardar Cambios</button>
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
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $linea['tipo_adquisicion'] === 'bien' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ $linea['tipo_adquisicion'] === 'bien' ? 'Bien' : 'Servicio' }}
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
                                    <option value="">Ninguno</option>
                                    @foreach ($this->municipios as $m)
                                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-sm mb-3">
                            <div class="flex-1 max-w-xs">
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

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6">
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
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 text-center">
            <p class="text-gray-500 dark:text-gray-400">Esta factura no está en estado borrador y no puede ser editada.</p>
            <a href="{{ route('facturas') }}" class="mt-4 inline-block text-violet-600 hover:text-violet-700 text-sm font-medium">Volver al listado</a>
        </div>
    @endif
</div>
