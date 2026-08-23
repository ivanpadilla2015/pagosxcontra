<?php

use App\Models\Acta;
use App\Models\Contrato;
use App\Models\Dependencia;
use App\Models\Factura;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $facturaId = null;
    public ?Factura $factura = null;
    public ?Contrato $contrato = null;

    // Datos del acta
    public ?int $actaId = null;
    public string $numero = '';
    public ?int $dependencia_id = null;
    public string $nombre_entrega = '';
    public string $cargo_entrega = '';
    public string $en_calidad_de = '';
    public string $fecha = '';
    public string $hora = '';
    public ?string $inspeccion_visual = null;
    public ?string $informes_laboratorio = null;
    public ?string $certificacion_expedida = null;

    public bool $guardando = false;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->cargarFactura($id);
        }
    }

    public function getDependenciasProperty()
    {
        $user = Auth::user();
        $regionalId = $user->regional_id ?? null;

        if (!$regionalId) {
            return Dependencia::orderBy('name')->get();
        }

        return Dependencia::where('regional_id', $regionalId)->orderBy('name')->get();
    }

    private function cargarFactura(int $facturaId): void
    {
        $factura = Factura::with(['contrato.proveedor', 'lineas.itemcontrato.producto', 'dependencia'])
            ->find($facturaId);

        if (!$factura) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Factura no encontrada.');
            return;
        }

        $this->facturaId = $facturaId;
        $this->factura = $factura;
        $this->contrato = $factura->contrato;

        // Verificar si ya tiene acta
        $actaExistente = Acta::where('factura_id', $facturaId)->first();
        if ($actaExistente) {
            $this->cargarActa($actaExistente);
            return;
        }

        // Valores por defecto para acta nueva
        $this->dependencia_id = $factura->dependencia_id;
        $this->fecha = now()->format('Y-m-d');
        $this->hora = now()->format('H:i');
        $this->numero = ($this->contrato->cansecu_actas + 1) . '-' . date('Y');
    }

    private function cargarActa(Acta $acta): void
    {
        $this->actaId = $acta->id;
        $this->numero = $acta->numero;
        $this->dependencia_id = $acta->dependencia_id;
        $this->nombre_entrega = $acta->nombre_entrega;
        $this->cargo_entrega = $acta->cargo_entrega;
        $this->en_calidad_de = $acta->en_calidad_de;
        $this->fecha = $acta->fecha->format('Y-m-d');
        $this->hora = $acta->hora;
        $this->inspeccion_visual = $acta->inspeccion_visual;
        $this->informes_laboratorio = $acta->informes_laboratorio;
        $this->certificacion_expedida = $acta->certificacion_expedida;
    }

    public function guardar(): void
    {
        if (!$this->factura || !$this->contrato || $this->guardando) return;

        $this->guardando = true;

        if (empty(trim($this->nombre_entrega))) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe ingresar el nombre de quien entrega.');
            $this->guardando = false;
            return;
        }

        if (empty(trim($this->cargo_entrega))) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe ingresar el cargo de quien entrega.');
            $this->guardando = false;
            return;
        }

        if (empty(trim($this->en_calidad_de))) {
            $this->dispatch('alerta', tipo: 'error', mensaje: 'Debe ingresar el campo "En calidad de".');
            $this->guardando = false;
            return;
        }

        $datos = [
            'numero'                 => $this->numero,
            'factura_id'             => $this->facturaId,
            'contrato_id'            => $this->contrato->id,
            'dependencia_id'         => $this->dependencia_id,
            'nombre_entrega'         => trim($this->nombre_entrega),
            'cargo_entrega'          => trim($this->cargo_entrega),
            'en_calidad_de'          => trim($this->en_calidad_de),
            'fecha'                  => $this->fecha,
            'hora'                   => $this->hora,
            'inspeccion_visual'      => $this->inspeccion_visual ?: null,
            'informes_laboratorio'   => $this->informes_laboratorio ?: null,
            'certificacion_expedida' => $this->certificacion_expedida ?: null,
            'user_id'                => Auth::id(),
        ];

        if ($this->actaId) {
            Acta::findOrFail($this->actaId)->update($datos);
            $this->dispatch('alerta', tipo: 'success', mensaje: 'Acta ' . $this->numero . ' actualizada correctamente.');
        } else {
            Acta::create($datos);
            $this->contrato->update(['cansecu_actas' => $this->contrato->cansecu_actas + 1]);
            $this->dispatch('alerta', tipo: 'success', mensaje: 'Acta ' . $this->numero . ' creada correctamente.');
        }

        $this->guardando = false;
    }

    public function render()
    {
        return view('components.contratos.acta-editar');
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="flex items-center mb-6">
        <a href="{{ route('actas') }}" class="text-violet-500 hover:text-violet-600 mr-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Acta de Recibo</h1>
            @if ($numero)
                <p class="text-sm text-gray-500 dark:text-gray-400">N° {{ $numero }}</p>
            @endif
        </div>
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
                 'bg-rose-50 border-rose-300 text-rose-800 dark:bg-rose-900/60 dark:border-rose-600 dark:text-rose-200': tipo === 'error'
             }">
            <template x-if="tipo === 'success'">
                <svg class="w-6 h-6 flex-shrink-0 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </template>
            <template x-if="tipo === 'error'">
                <svg class="w-6 h-6 flex-shrink-0 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </template>
            <p class="text-sm font-medium" x-text="mensaje"></p>
        </div>
    </div>

    @if ($factura)
        {{-- Datos del contrato y factura --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Datos de la Factura</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Contrato</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">N° Factura</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ explode('-', $factura->numero)[1] ?? $factura->numero }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $factura->proveedor->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha Factura</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $factura->fecha->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Datos del acta --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Datos del Acta</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° Acta</label>
                    <input type="text" wire:model="numero" class="form-input w-full bg-gray-50 dark:bg-gray-700" readonly />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                    <input type="date" wire:model="fecha" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora *</label>
                    <input type="time" wire:model="hora" class="form-input w-full" />
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dependencia / Comedor</label>
                    <select wire:model="dependencia_id" class="form-select w-full">
                        <option value="">Ninguna</option>
                        @foreach ($this->dependencias as $dep)
                            <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre quien entrega *</label>
                    <input type="text" wire:model="nombre_entrega" placeholder="Ej: Juan Pérez" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargo *</label>
                    <input type="text" wire:model="cargo_entrega" placeholder="Ej: Despachador" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">En calidad de *</label>
                    <input type="text" wire:model="en_calidad_de" placeholder="Ej: Representante legal" class="form-input w-full" />
                </div>
            </div>
        </div>

        {{-- Productos de la factura --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 mb-6">
            <div class="p-6 pb-0">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Productos Recibidos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table-auto w-full">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-center">Cant.</th>
                            <th class="px-4 py-3 text-right">V. Unitario</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach ($factura->lineas as $linea)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $linea->itemcontrato->producto->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $linea->cantidad }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($linea->valor_base / max(1, $linea->cantidad), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($linea->valor_iva, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-gray-100">$ {{ number_format($linea->valor_con_iva, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- Totales --}}
            <div class="p-6 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                <div class="flex justify-end gap-6 text-sm">
                    <div class="text-right">
                        <span class="text-gray-500 dark:text-gray-400">Subtotal:</span>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($factura->subtotal, 2, ',', '.') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-gray-500 dark:text-gray-400">IVA:</span>
                        <p class="font-semibold text-gray-800 dark:text-gray-100">$ {{ number_format($factura->total_iva, 2, ',', '.') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-gray-500 dark:text-gray-400">Total:</span>
                        <p class="font-bold text-emerald-600 dark:text-emerald-400">$ {{ number_format($factura->subtotal + $factura->total_iva, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Observaciones --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Observaciones</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Inspección visual realizada</label>
                    <input type="text" wire:model="inspeccion_visual" placeholder="Opcional" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Informes de laboratorio realizados</label>
                    <input type="text" wire:model="informes_laboratorio" placeholder="Opcional" class="form-input w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Certificación expedida</label>
                    <input type="text" wire-model="certificacion_expedida" placeholder="Opcional" class="form-input w-full" />
                </div>
            </div>
        </div>

        {{-- Botones --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('actas') }}" class="px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Volver
            </a>
            <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:loading.class="opacity-50" class="px-5 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white font-medium transition disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="guardar">{{ $actaId ? 'Actualizar' : 'Crear' }} Acta</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </button>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-12 text-center">
            <p class="text-gray-500 dark:text-gray-400">Cargando factura...</p>
        </div>
    @endif
</div>
