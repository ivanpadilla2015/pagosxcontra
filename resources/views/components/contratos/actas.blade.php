<?php

use App\Models\Acta;
use App\Models\Contrato;
use App\Traits\FiltrablePorRegional;
use Livewire\Component;

new class extends Component
{
    use FiltrablePorRegional;

    public string $numcontrato = '';
    public ?Contrato $contrato = null;
    public string $contratoError = '';

    public function buscarContrato(): void
    {
        $this->contratoError = '';
        $this->contrato = null;

        $numero = trim($this->numcontrato);
        if ($numero === '') {
            $this->contratoError = 'Ingrese el número del contrato.';
            return;
        }

        $contrato = Contrato::with(['proveedor', 'facturas.proveedor'])
            ->where('numcontrato', $numero)
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->first();

        if (!$contrato) {
            $this->contratoError = 'No se encontró un contrato con el número ' . $numero . '.';
            return;
        }

        $this->contrato = $contrato;
    }

    public function getFacturasSinActaProperty()
    {
        if (!$this->contrato) return collect();

        $facturaIdsConActa = Acta::where('contrato_id', $this->contrato->id)
            ->pluck('factura_id');

        return $this->contrato->facturas()
            ->with('proveedor')
            ->whereNotIn('id', $facturaIdsConActa)
            ->where('estado', 'emitida')
            ->orderBy('fecha', 'desc')
            ->get();
    }

    public function render()
    {
        return view('components.contratos.actas');
    }
};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Acta de Recibo a Satisfacción</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Genere actas de recibo a partir de facturas emitidas.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-900/20 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Buscar contrato --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Buscar Contrato</h2>
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de contrato</label>
                <input type="text" wire:model="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: 010-009-2026" class="form-input w-full" />
            </div>
            <button type="button" wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">Buscar</button>
        </div>
        @if ($contratoError)
            <p class="mt-2 text-sm text-rose-500">{{ $contratoError }}</p>
        @endif
    </div>

    {{-- Resultado --}}
    @if ($contrato)
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Datos del Contrato</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Número</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->numcontrato }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->proveedor->nombre ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Actas creadas</p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $contrato->cansecu_actas }}</p>
                </div>
            </div>
        </div>

        {{-- Facturas sin acta --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <div class="p-6 pb-0">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Facturas sin Acta</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccione una factura para crear el acta de recibo.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-auto w-full">
                    <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">N° Factura</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse ($this->facturasSinActa as $factura)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">
                                    {{ explode('-', $factura->numero)[1] ?? $factura->numero }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factura->proveedor->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($factura->subtotal, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($factura->total_iva, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-gray-100">$ {{ number_format($factura->subtotal + $factura->total_iva, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('actas.editar', $factura->id) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-400 transition">
                                        Crear Acta
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Todas las facturas ya tienen acta de recibo, o no hay facturas emitidas para este contrato.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actas ya creadas --}}
        @php
            $actasExistentes = \App\Models\Acta::with(['factura', 'factura.proveedor'])->where('contrato_id', $contrato->id)->orderBy('numero', 'desc')->get();
        @endphp

        @if ($actasExistentes->count() > 0)
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60 mt-6">
                <div class="p-6 pb-0">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Actas Creadas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-auto w-full">
                        <thead class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold border-t border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left">N° Acta</th>
                            <th class="px-4 py-3 text-left">N° Factura</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Proveedor</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">IVA</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700/60">
                        @foreach ($actasExistentes as $acta)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $acta->numero }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ explode('-', $acta->factura->numero)[1] ?? $acta->factura->numero }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $acta->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $acta->factura->proveedor->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($acta->factura->subtotal, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">$ {{ number_format($acta->factura->total_iva, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800 dark:text-gray-100">$ {{ number_format($acta->factura->subtotal + $acta->factura->total_iva, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('actas.editar', $acta->factura_id) }}" class="text-violet-500 hover:text-violet-600 mr-3" title="Ver/Editar">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                                    </a>
                                    <a href="{{ route('actas.imprimir', $acta->id) }}" target="_blank" class="text-emerald-500 hover:text-emerald-600" title="Imprimir Acta">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
