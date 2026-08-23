<?php

use Livewire\Component;
use App\Models\Traslado;
use App\Models\Movirubro;
use App\Models\Contrato;
use App\Models\Registro;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination;

    public string $tab = 'propuesta';
    public string $search = '';
    public string $sortField = 'id';
    public string $sortDirection = 'desc';

    // Modales
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $confirmDeleteId = null;

    // Búsqueda de contrato
    public string $numcontrato = '';
    public ?int $contrato_id = null;
    public bool $contrato_encontrado = false;
    public array $resultados_busqueda = [];

    // Movirubros del contrato
    public array $movirubros_existentes = [];

    // Traslado
    public ?int $origen_movirubro_id = null;
    public ?int $destino_movirubro_id = null;
    public float $valor_traslado = 0;
    public string $observaciones = '';

    // ------------------------------------------------------------------
    // Computed
    // ------------------------------------------------------------------

    #[Computed]
    public function trasladosPropuestos()
    {
        return Traslado::with(['contrato', 'movirubroOrigen.rubro', 'movirubroDestino.rubro', 'userPropone'])
            ->where('estado', 'propuesto')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('contrato', fn ($cq) => $cq->where('numcontrato', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function misPropuestas()
    {
        return Traslado::with(['contrato', 'movirubroOrigen.rubro', 'movirubroDestino.rubro', 'userAprueba'])
            ->where('user_propone_id', auth()->id())
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('contrato', fn ($cq) => $cq->where('numcontrato', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function esPresupuesto(): bool
    {
        return auth()->user()->hasRole('presupuesto');
    }

    // ------------------------------------------------------------------
    // Búsqueda de contrato
    // ------------------------------------------------------------------

    public function buscarContrato(): void
    {
        if (strlen($this->numcontrato) < 2) {
            $this->resultados_busqueda = [];
            $this->contrato_encontrado = false;
            return;
        }

        $this->resultados_busqueda = Contrato::with('proveedor')
            ->where('numcontrato', 'like', '%' . $this->numcontrato . '%')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'numcontrato' => $c->numcontrato,
                'proveedor' => $c->proveedor,
            ])
            ->toArray();
    }

    public function seleccionarContrato($id): void
    {
        $contrato = Contrato::with(['registros.movirubros.rubro', 'registros.tiporegistro'])->find($id);
        if (!$contrato) return;

        $this->contrato_id = $contrato->id;
        $this->numcontrato = $contrato->numcontrato;
        $this->contrato_encontrado = true;
        $this->resultados_busqueda = [];

        $this->cargarMovirubros();
    }

    private function cargarMovirubros(): void
    {
        $contrato = Contrato::with(['registros.movirubros.rubro', 'registros.tiporegistro'])->find($this->contrato_id);
        if (!$contrato) return;

        $this->movirubros_existentes = [];

        foreach ($contrato->registros as $registro) {
            if ($registro->tiporegistro_id == 3) continue; // Skip Reducciones

            foreach ($registro->movirubros as $movirubro) {
                $this->movirubros_existentes[] = [
                    'movirubro_id' => $movirubro->id,
                    'rubro_id' => $movirubro->rubro_id,
                    'nombre_rubro' => $movirubro->rubro->nombre_rubro ?? '',
                    'codigo_rubro' => $movirubro->rubro->codigo_rubro ?? '',
                    'saldo_rubro' => $movirubro->saldo_rubro,
                    'registro_tipo' => $registro->tiporegistro->name ?? '',
                    'registro_numero' => $registro->numero_reg,
                ];
            }
        }
    }

    // ------------------------------------------------------------------
    // Proponer traslado
    // ------------------------------------------------------------------

    public function propnerTraslado(): void
    {
        if (!$this->contrato_id) {
            session()->flash('error', 'Debe seleccionar un contrato.');
            return;
        }

        if (!$this->origen_movirubro_id || !$this->destino_movirubro_id) {
            session()->flash('error', 'Debe seleccionar el rubro origen y destino.');
            return;
        }

        if ($this->origen_movirubro_id == $this->destino_movirubro_id) {
            session()->flash('error', 'El rubro origen y destino deben ser diferentes.');
            return;
        }

        if ($this->valor_traslado <= 0) {
            session()->flash('error', 'El valor a transferir debe ser mayor a cero.');
            return;
        }

        $origen = Movirubro::find($this->origen_movirubro_id);
        if (!$origen || $origen->saldo_rubro < $this->valor_traslado) {
            session()->flash('error', 'El rubro origen no tiene saldo suficiente. Saldo disponible: $' . number_format($origen->saldo_rubro ?? 0, 2, ',', '.'));
            return;
        }

        Traslado::create([
            'contrato_id' => $this->contrato_id,
            'movirubro_origen_id' => $this->origen_movirubro_id,
            'movirubro_destino_id' => $this->destino_movirubro_id,
            'valor' => $this->valor_traslado,
            'estado' => 'propuesto',
            'user_propone_id' => auth()->id(),
            'observaciones' => $this->observaciones ?: null,
        ]);

        $this->reset(['origen_movirubro_id', 'destino_movirubro_id', 'valor_traslado', 'observaciones']);
        $this->cargarMovirubros();

        session()->flash('message', 'Traslado propuesto exitosamente. Pendiente de aprobación por presupuesto.');
    }

    // ------------------------------------------------------------------
    // Aprobar / Rechazar
    // ------------------------------------------------------------------

    public function aprobar(int $id): void
    {
        $traslado = Traslado::findOrFail($id);

        $origen = Movirubro::find($traslado->movirubro_origen_id);
        if (!$origen || $origen->saldo_rubro < $traslado->valor) {
            session()->flash('error', 'El rubro origen ya no tiene saldo suficiente para este traslado.');
            return;
        }

        DB::transaction(function () use ($traslado) {
            // 1. Crear Registro (Traslado) solo para historial
            $registro = Registro::create([
                'numero_reg' => 'TR-' . $traslado->id,
                'fecha_reg' => now(),
                'newplazoejecucion' => now(),
                'valor_reg' => $traslado->valor,
                'estado' => true,
                'tiporegistro_id' => 4,
                'contrato_id' => $traslado->contrato_id,
            ]);

            // 2. Mover saldo: decrementar origen, incrementar destino
            Movirubro::where('id', $traslado->movirubro_origen_id)
                ->decrement('saldo_rubro', $traslado->valor);
            Movirubro::where('id', $traslado->movirubro_destino_id)
                ->increment('saldo_rubro', $traslado->valor);

            // 3. Actualizar traslado
            $traslado->update([
                'estado' => 'aprobado',
                'user_aprueba_id' => auth()->id(),
                'fecha_aprobacion' => now(),
                'registro_id' => $registro->id,
            ]);
        });

        session()->flash('message', 'Traslado aprobado y ejecutado exitosamente.');
    }

    public function rechazar(int $id): void
    {
        $traslado = Traslado::findOrFail($id);
        $traslado->update([
            'estado' => 'rechazado',
            'user_aprueba_id' => auth()->id(),
            'fecha_aprobacion' => now(),
        ]);

        session()->flash('message', 'Traslado rechazado.');
    }

    // ------------------------------------------------------------------
    // Eliminar (solo propuestos propios)
    // ------------------------------------------------------------------

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function eliminar(): void
    {
        $traslado = Traslado::findOrFail($this->confirmDeleteId);

        if ($traslado->estado !== 'propuesto') {
            session()->flash('error', 'Solo se pueden eliminar traslados propuestos.');
            $this->showDeleteModal = false;
            return;
        }

        $traslado->delete();

        session()->flash('message', 'Traslado eliminado.');
        $this->confirmDeleteId = null;
        $this->showDeleteModal = false;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

};
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Traslados de Saldo</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Transferir saldo entre rubros del mismo contrato.</p>
    </div>

    {{-- Toast --}}
    @if (session('message'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-300">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-900/30 dark:border-rose-700 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    {{-- Pestañas --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-700">
        <button wire:click="$set('tab', 'propuesta')" class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition {{ $tab === 'propuesta' ? 'bg-violet-50 text-violet-700 border-b-2 border-violet-500 dark:bg-violet-900/20 dark:text-violet-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
            Proponer Traslado
        </button>
        <button wire:click="$set('tab', 'propuestas')" class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition {{ $tab === 'propuestas' ? 'bg-violet-50 text-violet-700 border-b-2 border-violet-500 dark:bg-violet-900/20 dark:text-violet-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
            Mis Propuestas
        </button>
        @if ($this->esPresupuesto)
            <button wire:click="$set('tab', 'autorizar')" class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition {{ $tab === 'autorizar' ? 'bg-emerald-50 text-emerald-700 border-b-2 border-emerald-500 dark:bg-emerald-900/20 dark:text-emerald-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Autorizar Traslados
            </button>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- PESTAÑA: PROponer TRASLADO --}}
    {{-- ============================================================ --}}
    @if ($tab === 'propuesta')
        {{-- Buscar contrato --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">1. Buscar Contrato</h2>
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de contrato</label>
                    <input type="text" wire:model="numcontrato" wire:keydown.enter="buscarContrato" placeholder="Ej: 010-009-2026" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" />
                </div>
                <button type="button" wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">Buscar</button>
            </div>
            @if (count($resultados_busqueda) > 0)
                <div class="mt-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-lg max-h-60 overflow-y-auto">
                    @foreach ($resultados_busqueda as $r)
                        <button wire:click="seleccionarContrato({{ $r['id'] }})" class="w-full px-4 py-3 text-left hover:bg-violet-50 dark:hover:bg-violet-900/20 border-b border-gray-100 dark:border-gray-600 last:border-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $r['numcontrato'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $r['proveedor']['nombre'] ?? '-' }}</p>
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($contrato_encontrado)
                <div class="mt-4 rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700/60 px-4 py-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Contrato: <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $numcontrato }}</span></p>
                </div>
            @endif
        </div>

        @if ($contrato_encontrado && count($movirubros_existentes) > 0)
            {{-- Tabla de movirubros --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">2. Rubros Disponibles</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left">Código</th>
                                <th class="px-4 py-3 text-left">Rubro</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3 text-left">Registro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($movirubros_existentes as $m)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $m['codigo_rubro'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $m['nombre_rubro'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $m['saldo_rubro'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        ${{ number_format($m['saldo_rubro'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $m['registro_tipo'] }} — {{ $m['registro_numero'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Formulario de traslado --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">3. Configurar Traslado</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rubro Origen (de donde sale) *</label>
                        <select wire:model="origen_movirubro_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Seleccionar...</option>
                            @foreach ($movirubros_existentes as $m)
                                @if ($m['saldo_rubro'] > 0)
                                    <option value="{{ $m['movirubro_id'] }}">{{ $m['codigo_rubro'] }} — {{ $m['nombre_rubro'] }} (Saldo: ${{ number_format($m['saldo_rubro'], 2, ',', '.') }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rubro Destino (a donde llega) *</label>
                        <select wire:model="destino_movirubro_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Seleccionar...</option>
                            @foreach ($movirubros_existentes as $m)
                                <option value="{{ $m['movirubro_id'] }}">{{ $m['codigo_rubro'] }} — {{ $m['nombre_rubro'] }} (Saldo: ${{ number_format($m['saldo_rubro'], 2, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor a Transferir *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="valor_traslado" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" placeholder="Ej: 5000000" />
                        @if ($origen_movirubro_id)
                            @php $origen = collect($movirubros_existentes)->firstWhere('movirubro_id', $origen_movirubro_id); @endphp
                            @if ($origen)
                                <p class="mt-1 text-xs text-gray-400">Saldo disponible: ${{ number_format($origen['saldo_rubro'], 2, ',', '.') }}</p>
                            @endif
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones</label>
                        <input type="text" wire:model="observaciones" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" placeholder="Motivo del traslado (opcional)" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button wire:click="propnerTraslado" wire:confirm="¿Proponer este traslado?" wire:loading.attr="disabled" class="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                        <span wire:loading.remove>Proponer Traslado</span>
                        <span wire:loading>Proponiendo...</span>
                    </button>
                </div>
            </div>
        @elseif ($contrato_encontrado)
            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700/60 p-4 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay movirubros registrados para este contrato.</p>
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- PESTAÑA: MIS PROPUESTAS --}}
    {{-- ============================================================ --}}
    @if ($tab === 'propuestas')
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <input type="text" wire:model.live="search" placeholder="Buscar por contrato..." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left">Contrato</th>
                            <th class="px-4 py-3 text-left">Origen</th>
                            <th class="px-4 py-3 text-left">Destino</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-4 py-3 text-left">Aprobado por</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->misPropuestas as $t)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $t->contrato->numcontrato ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $t->movirubroOrigen->rubro->codigo_rubro ?? '' }} — {{ $t->movirubroOrigen->rubro->nombre_rubro ?? '' }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $t->movirubroDestino->rubro->codigo_rubro ?? '' }} — {{ $t->movirubroDestino->rubro->nombre_rubro ?? '' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">${{ number_format($t->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $t->estado === 'propuesto' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' :
                                           ($t->estado === 'aprobado' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                           'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400') }}">
                                        {{ ucfirst($t->estado) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $t->userAprueba->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($t->estado === 'propuesto')
                                        <button wire:click="confirmDelete({{ $t->id }})" wire:confirm="¿Eliminar esta propuesta?" class="text-rose-500 hover:text-rose-700" title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No tienes propuestas de traslado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $this->misPropuestas->links() }}
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- PESTAÑA: AUTORIZAR TRASLADOS (solo presupuesto) --}}
    {{-- ============================================================ --}}
    @if ($tab === 'autorizar' && $this->esPresupuesto)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <input type="text" wire:model.live="search" placeholder="Buscar por contrato..." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-violet-500 focus:ring-violet-500" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left">Contrato</th>
                            <th class="px-4 py-3 text-left">Origen</th>
                            <th class="px-4 py-3 text-left">Destino</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3 text-left">Propuesto por</th>
                            <th class="px-4 py-3 text-left">Observación</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->trasladosPropuestos as $t)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $t->contrato->numcontrato ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-800 dark:text-gray-100">{{ $t->movirubroOrigen->rubro->codigo_rubro ?? '' }} — {{ $t->movirubroOrigen->rubro->nombre_rubro ?? '' }}</p>
                                    <p class="text-xs text-gray-400">Saldo: ${{ number_format($t->movirubroOrigen->saldo_rubro ?? 0, 2, ',', '.') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-800 dark:text-gray-100">{{ $t->movirubroDestino->rubro->codigo_rubro ?? '' }} — {{ $t->movirubroDestino->rubro->nombre_rubro ?? '' }}</p>
                                    <p class="text-xs text-gray-400">Saldo: ${{ number_format($t->movirubroDestino->saldo_rubro ?? 0, 2, ',', '.') }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-violet-600 dark:text-violet-400">${{ number_format($t->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $t->userPropone->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-[200px] truncate">{{ $t->observaciones ?? '—' }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <button wire:click="aprobar({{ $t->id }})" wire:confirm="¿Aprobar y ejecutar este traslado?" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 transition mr-2" title="Aprobar">
                                        Aprobar
                                    </button>
                                    <button wire:click="rechazar({{ $t->id }})" wire:confirm="¿Rechazar este traslado?" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-400 transition" title="Rechazar">
                                        Rechazar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay traslados pendientes de aprobación.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $this->trasladosPropuestos->links() }}
            </div>
        </div>
    @endif

    {{-- Modal Confirmar Eliminar --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="$set('showDeleteModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 dark:bg-rose-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Eliminar Traslado</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">¿Estás seguro de eliminar esta propuesta de traslado? Esta acción no se puede deshacer.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                        <button wire:click="eliminar" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-base font-medium text-white hover:bg-rose-700 sm:w-auto sm:text-sm transition">Eliminar</button>
                        <button wire:click="$set('showDeleteModal', false)" class="w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:w-auto sm:text-sm transition">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
