<?php

use App\Models\Informe;
use App\Models\InformeRegistro;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Registro;
use App\Traits\FiltrablePorRegional;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use FiltrablePorRegional;

    public string $numcontrato = '';
    public ?int $contratoId = null;
    public string $contratoInfo = '';
    public bool $contratoEncontrado = false;

    public bool $modalOpen = false;
    public bool $deleteModalOpen = false;
    public ?int $editingId = null;
    public ?int $informeToDeleteId = null;
    public string $informeToDeleteName = '';

    // Paso del modal: 1 = buscar contrato, 2 = formulario
    public int $paso = 1;
    public string $busquedaContrato = '';
    public string $contratoInfoModal = '';

    public string $cansecu_infor = '';
    public string $fecha = '';
    public ?int $tramite_pago_id = null;
    public string $estado = 'abierto';
    public float $total_info = 0;
    public float $saldo_viene = 0;
    public string $porcentaje_cumplimiento = '';
    public string $mes_ejecucion = '';
    public string $corresponde_texto_periodo = '';
    public string $novedad = '';
    public string $fiducia = '';
    public string $infopersonal = '';
    public string $infoaiu = '';
    public string $anexos = '';
    public string $recomendacion = '';

    // Validación meses faltantes
    public array $mesesFaltantes = [];
    public string $errorMesesFaltantes = '';
    public bool $modalConfirmarMesesFaltantes = false;
    public bool $guardandoInforme = false;
    public string $errorBusqueda = '';
    public string $errorDuplicado = '';

    // Modal de alerta global
    public bool $modalAlerta = false;
    public string $modalAlertaTitulo = '';
    public string $modalAlertaMensaje = '';
    public string $modalAlertaTipo = 'error';

    // Modal de pagos del informe
    public bool $modalPagosOpen = false;
    public string $modalPagosInforme = '';
    public array $modalPagosLista = [];

    // Obligaciones
    public array $obligacionesList = [];
    public bool $modalObligacionesMasivoOpen = false;
    public string $nuevoEntregable = '';
    public string $nuevoConfirmar = 'SI';
    public bool $modalEditarObligacionOpen = false;
    public int $editarObligacionIndex = -1;
    public string $editEntregable = '';
    public string $editConfirmar = 'SI';

    // Riesgos
    public array $riesgosList = [];
    public bool $modalRiesgosMasivoOpen = false;
    public string $nuevoTratamiento = '';
    public bool $modalEditarRiesgoOpen = false;
    public int $editarRiesgoIndex = -1;
    public string $editTratamiento = '';

    protected function rules(): array
    {
        return [
            'numcontrato' => ['required', 'string'],
            'cansecu_infor' => ['required', 'string', 'max:5'],
            'fecha' => ['required', 'date'],
            'estado' => ['required', 'string', 'max:20'],
            'total_info' => ['required', 'numeric', 'min:0'],
            'saldo_viene' => ['required', 'numeric', 'min:0'],
            'porcentaje_cumplimiento' => ['required', 'string'],
            'mes_ejecucion' => ['required', 'string', 'max:2'],
            'corresponde_texto_periodo' => ['required', 'string'],
        ];
    }

    #[Computed]
    public function informes()
    {
        return Informe::query()
            ->when($this->contratoId, fn ($q) => $q->where('contrato_id', $this->contratoId))
            ->orderByDesc('fecha')
            ->paginate(10);
    }

    #[Computed]
    public function ultimoCansecuInfor()
    {
        if (!$this->contratoId) return 0;
        $contrato = Contrato::find($this->contratoId);
        return $contrato ? $contrato->cansecu_infor : 0;
    }

    public function getPagosInforme($cansecuInfor)
    {
        return Pago::where('contrato_id', $this->contratoId)
            ->where('cansecu_infor', $cansecuInfor)
            ->where('estado', 'cerrado')
            ->get();
    }

    #[Computed]
    public function pagosAgrupados()
    {
        if (!$this->contratoId || empty($this->cansecu_infor)) return collect();

        // Filtra pagos cerrados que correspondan al consecutivo del informe que se está creando/editando
        return Pago::where('contrato_id', $this->contratoId)
            ->where('estado', 'cerrado')
            ->where('cansecu_infor', $this->cansecu_infor)
            ->get();
    }

    #[Computed]
    public function tramites()
    {
        if (!$this->contratoId) {
            return collect();
        }

        return \App\Models\TramitePago::where('contrato_id', $this->contratoId)->get();
    }

    #[Computed]
    public function obligaciones()
    {
        if (!$this->contratoId) {
            return collect();
        }

        return \App\Models\Obligacion::where('contrato_id', $this->contratoId)->get();
    }

    public function mostrarAlerta(string $tipo, string $titulo, string $mensaje): void
    {
        $this->modalAlertaTipo = $tipo;
        $this->modalAlertaTitulo = $titulo;
        $this->modalAlertaMensaje = $mensaje;
        $this->modalAlerta = true;
    }

    public function cerrarAlerta(): void
    {
        $this->modalAlerta = false;
    }

    public function verPagosInforme(int $informeId): void
    {
        $informe = Informe::findOrFail($informeId);
        $this->modalPagosInforme = $informe->cansecu_infor . ' - ' . $informe->corresponde_texto_periodo;
        $this->modalPagosLista = Pago::where('contrato_id', $informe->contrato_id)
            ->where('cansecu_infor', $informe->cansecu_infor)
            ->where('estado', 'cerrado')
            ->get()
            ->toArray();
        $this->modalPagosOpen = true;
    }

    public function cerrarModalPagos(): void
    {
        $this->modalPagosOpen = false;
        $this->modalPagosInforme = '';
        $this->modalPagosLista = [];
    }

    public function buscarContrato(): void
    {
        $this->resetValidation();
        $this->contratoEncontrado = false;
        $this->contratoId = null;
        $this->contratoInfo = '';

        $this->validateOnly('numcontrato');

        $contrato = Contrato::where('numcontrato', $this->numcontrato)
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->first();

        if (!$contrato) {
            session()->flash('error', 'No se encontró un contrato con ese número.');
            return;
        }

        $this->contratoId = $contrato->id;
        $this->contratoEncontrado = true;
        $proveedor = $contrato->proveedor ? $contrato->proveedor->nombre : 'N/A';
        $valor = '$' . number_format($contrato->valorTotal, 2, ',', '.');
        $saldo = '$' . number_format($contrato->saldo, 2, ',', '.');
        $this->contratoInfo = "Contrato: {$contrato->numcontrato} | Proveedor: {$proveedor} | Valor: {$valor} | Saldo: {$saldo}";
    }

    public function buscarContratoModal(): void
    {
        $this->errorBusqueda = '';
        $contrato = Contrato::where('numcontrato', $this->busquedaContrato)
            ->when(!auth()->user()->hasRole('admin'), function ($q) {
                $q->whereHas('user', fn($q2) => $q2->where('regional_id', auth()->user()->regional_id));
            })
            ->first();

        if (!$contrato) {
            $this->errorBusqueda = 'No se encontró un contrato con ese número.';
            return;
        }

        if ($contrato->obligaciones->isEmpty() || $contrato->riesgos->isEmpty()) {
            $this->errorBusqueda = 'El contrato no tiene obligaciones y/o riesgos registrados. Debe crearlos antes de generar un informe.';
            return;
        }

        $this->contratoId = $contrato->id;
        $this->contratoEncontrado = false;
        $this->paso = 2;

        $proveedor = $contrato->proveedor ? $contrato->proveedor->nombre : 'N/A';
        $valor = '$' . number_format($contrato->valorTotal, 2, ',', '.');
        $saldo = '$' . number_format($contrato->saldo, 2, ',', '.');
        $this->contratoInfoModal = "Contrato: {$contrato->numcontrato} | Proveedor: {$proveedor} | Valor: {$valor} | Saldo: {$saldo}";

        $this->cansecu_infor = $contrato->cansecu_infor + 1;
        $this->fecha = now()->format('Y-m-d');

        $this->total_info = Pago::where('contrato_id', $this->contratoId)
            ->where('estado', 'cerrado')
            ->where('cansecu_infor', $this->cansecu_infor)
            ->sum('valor_total');

        // saldo_viene = suma de total_info de informes anteriores (no anulados)
        $this->saldo_viene = Informe::where('contrato_id', $this->contratoId)
            ->where('estado', '!=', 'anulado')
            ->where('cansecu_infor', '<', $this->cansecu_infor)
            ->sum('total_info');

        $this->novedad = 'N/A';
        $this->fiducia = 'N/A';
        $this->infopersonal = 'El servicio fue desarrollado por el personal asignado por la empresa sin novedad especial';
        $this->infoaiu = 'Ninguna';
        $this->anexos = 'Ninguno';
        $this->recomendacion = 'Ninguna';

        // % Cumplimiento: (saldo_viene + total_info) / valorTotal * 100
        $ejecutado = $this->saldo_viene + $this->total_info;
        $this->porcentaje_cumplimiento = $contrato->valorTotal > 0
            ? round(($ejecutado / $contrato->valorTotal) * 100, 2)
            : 0;

        $this->obligacionesList = $contrato->obligaciones->map(fn ($o) => [
            'id' => $o->id,
            'numeral' => $o->numeral,
            'obligacion_deta' => $o->obligacion_deta,
            'entregable' => $this->total_info > 0 ? $o->entregable : 'No se requirió en este periodo',
            'confirmar' => $this->total_info > 0 ? 'SI' : 'NO',
        ])->toArray();

        $this->riesgosList = $contrato->riesgos->map(fn ($r) => [
            'id' => $r->id,
            'tipo' => $r->tipo,
            'descripcion' => $r->descripcion,
            'tratamiento' => $r->tratamiento,
            'responsable' => $r->responsable,
            'periodicidad' => $r->periodicidad,
        ])->toArray();
    }

    public function volverPaso1(): void
    {
        $this->paso = 1;
        $this->busquedaContrato = '';
        $this->contratoInfoModal = '';
        $this->obligacionesList = [];
        $this->riesgosList = [];
        $this->mesesFaltantes = [];
        $this->errorMesesFaltantes = '';
        $this->modalConfirmarMesesFaltantes = false;
        $this->errorBusqueda = '';
    }

    public function updatedNumcontrato(): void
    {
        if (empty($this->numcontrato)) {
            $this->contratoEncontrado = false;
            $this->contratoId = null;
            $this->contratoInfo = '';
        }
    }

    public function updatedMesEjecucion(): void
    {
        $this->errorMesesFaltantes = '';
        $this->verificarMesesFaltantes();
    }

    public function verificarMesesFaltantes(): void
    {
        $this->mesesFaltantes = [];

        if (!$this->contratoId || empty($this->mes_ejecucion)) {
            return;
        }

        $contrato = Contrato::find($this->contratoId);
        if (!$contrato || !$contrato->fecha_inicio_contrato) {
            return;
        }

        $mesSeleccionado = (int) $this->mes_ejecucion;
        $anioSeleccionado = (int) explode('-', $this->fecha)[0];
        $inicio = $contrato->fecha_inicio_contrato;
        $mesInicio = (int) $inicio->format('m');
        $anioInicio = (int) $inicio->format('Y');

        $mesesRequeridos = [];
        $anio = $anioInicio;
        $mes = $mesInicio;

        // Excluimos el mes seleccionado porque ese es el que el usuario va a crear ahora
        while ($anio < $anioSeleccionado || ($anio === $anioSeleccionado && $mes < $mesSeleccionado)) {
            $mesesRequeridos[] = ['mes' => $mes, 'anio' => $anio];
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $anio++;
            }
        }

        $mesesExistentes = Informe::where('contrato_id', $this->contratoId)
            ->where('estado', '!=', 'anulado')
            ->get()
            ->map(fn ($i) => ['mes' => (int) $i->mes_ejecucion, 'anio' => (int) $i->fecha->format('Y')])
            ->unique(fn ($item) => $item['mes'] . '-' . $item['anio'])
            ->toArray();

        $existentesKeys = array_map(fn ($e) => $e['mes'] . '-' . $e['anio'], $mesesExistentes);

        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
            4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        foreach ($mesesRequeridos as $req) {
            $key = $req['mes'] . '-' . $req['anio'];
            if (!in_array($key, $existentesKeys)) {
                $this->mesesFaltantes[] = $nombresMeses[$req['mes']] . ' ' . $req['anio'];
            }
        }
    }

    /**
     * Abre modal de confirmación para crear informes faltantes masivamente.
     */
    public function crearMesesFaltantes(): void
    {
        $this->modalConfirmarMesesFaltantes = true;
    }

    /**
     * Cierra el modal de confirmación de meses faltantes.
     */
    public function closeModalConfirmarMeses(): void
    {
        $this->modalConfirmarMesesFaltantes = false;
    }

    /**
     * Crea todos los informes faltantes (con valor $0) dentro de una transacción.
     * También actualiza el cansecu_infor de pagos existentes que correspondan a cada mes.
     * Después de crearlos, recalcula los datos del formulario para el informe actual.
     */
    public function confirmarCrearMesesFaltantes(): void
    {
        if (!$this->contratoId) {
            session()->flash('error', 'No se ha seleccionado un contrato.');
            return;
        }

        $this->guardandoInforme = true;
        $contrato = Contrato::find($this->contratoId);
        $contratoId = $this->contratoId;
        $userId = auth()->id();
        $mesesFaltantes = $this->mesesFaltantes;
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
            4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        DB::transaction(function () use ($contrato, $contratoId, $userId, $mesesFaltantes, $meses) {
            // saldo_viene inicial = suma de total_info de informes ya existentes (no anulados)
            $saldoViene = Informe::where('contrato_id', $contratoId)
                ->where('estado', '!=', 'anulado')
                ->sum('total_info');

            foreach ($mesesFaltantes as $mesFaltante) {
                // Parsear "Marzo 2026" → nombreMes=Marzo, anio=2026
                $parts = explode(' ', $mesFaltante);
                $nombreMes = $parts[0];
                $anio = (int) $parts[1];
                $numMes = array_search($nombreMes, $meses);

                // Crear informe faltante con valor $0 y estado cerrado
                // Fecha = último día del mes, ajustado si cae en fin de semana
                $fechaInforme = Carbon::create($anio, $numMes, 1)->endOfMonth();
                while ($fechaInforme->isSaturday() || $fechaInforme->isSunday()) {
                    $fechaInforme->subDay();
                }

                // % Cumplimiento: (saldo_viene + total_info) / valorTotal * 100
                // total_info = 0 para faltantes, pero acumulamos saldo_viene para el siguiente
                $ejecutado = $saldoViene + 0;
                $porcentaje = $contrato->valorTotal > 0
                    ? round(($ejecutado / $contrato->valorTotal) * 100, 2)
                    : 0;

                $contrato->update(['cansecu_infor' => $contrato->cansecu_infor + 1]);
                $nuevoInforme = Informe::create([
                    'cansecu_infor' => $contrato->cansecu_infor,
                    'fecha' => $fechaInforme->format('Y-m-d'),
                    'contrato_id' => $contratoId,
                    'estado' => 'cerrado',
                    'total_info' => 0,
                    'saldo_viene' => $saldoViene,
                    'porcentaje_cumplimiento' => $porcentaje,
                    'mes_ejecucion' => str_pad($numMes, 2, '0', STR_PAD_LEFT),
                    'corresponde_texto_periodo' => $nombreMes . ' ' . $anio,
                    'novedad' => 'N/A',
                    'fiducia' => 'N/A',
                    'infopersonal' => 'El servicio fue desarrollado por el personal asignado por la empresa sin novedad especial',
                    'infoaiu' => 'Ninguna',
                    'anexos' => 'Ninguno',
                    'recomendacion' => 'Ninguna',
                    'user_id' => $userId,
                ]);

                $this->crearSnapshotRegistros($nuevoInforme);

                // Copiar obligaciones del contrato con confirmar = "NO"
                foreach ($contrato->obligaciones as $obligacion) {
                    $nuevoInforme->informeobligaciones()->create([
                        'numeral' => $obligacion->numeral,
                        'obligacion_deta' => $obligacion->obligacion_deta,
                        'entregable' => 'No se requirió en este periodo',
                        'confirmar' => 'NO',
                        'contrato_id' => $contratoId,
                    ]);
                }

                // Copiar riesgos del contrato
                foreach ($contrato->riesgos as $riesgo) {
                    $nuevoInforme->informeriesgos()->create([
                        'tipo' => $riesgo->tipo,
                        'descripcion' => $riesgo->descripcion,
                        'tratamiento' => $riesgo->tratamiento,
                        'responsable' => $riesgo->responsable,
                        'periodicidad' => $riesgo->periodicidad,
                    ]);
                }

                // Acumular: el siguiente informe faltante tendrá este saldo_viene + 0 (total_info del actual)
                $saldoViene += 0; // total_info es 0, pero si en el futuro cambia, ya está listo
            }

            // Mover TODOS los pagos cerrados que estaban en un consecutivo menor o igual
            // al último creado. El pago pasa al consecutivo del informe que se está creando
            // (el siguiente al último faltante creado)
            Pago::where('contrato_id', $contratoId)
                ->where('estado', 'cerrado')
                ->where('cansecu_infor', '<=', $contrato->cansecu_infor)
                ->update(['cansecu_infor' => $contrato->cansecu_infor + 1]);
        });

        // Limpiar estado y recalcular datos para el informe actual
        $this->mesesFaltantes = [];
        $this->errorMesesFaltantes = '';
        $this->modalConfirmarMesesFaltantes = false;
        $this->guardandoInforme = false;

        // Recargar datos del contrato (cansecu_infor ya se incrementó)
        $this->buscarContratoModal();
        session()->flash('message', 'Informes faltantes creados correctamente.');
    }

    public function openModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->reset([
            'cansecu_infor', 'fecha', 'tramite_pago_id', 'estado', 'total_info',
            'saldo_viene', 'porcentaje_cumplimiento', 'mes_ejecucion',
            'corresponde_texto_periodo', 'novedad', 'fiducia', 'infopersonal',
            'infoaiu', 'anexos', 'recomendacion', 'editingId', 'contratoInfoModal',
            'mesesFaltantes', 'errorMesesFaltantes', 'modalConfirmarMesesFaltantes'
        ]);
        $this->editingId = $id;
        $this->paso = 1;
        $this->busquedaContrato = '';

        if ($id) {
            $informe = Informe::findOrFail($id);
            $this->contratoId = $informe->contrato_id;
            $this->cansecu_infor = $informe->cansecu_infor;
            $this->fecha = $informe->fecha->format('Y-m-d');
            $this->tramite_pago_id = $informe->tramite_pago_id;
            $this->estado = $informe->estado;
            $this->total_info = $informe->total_info;
            $this->saldo_viene = $informe->saldo_viene;
            $this->porcentaje_cumplimiento = $informe->porcentaje_cumplimiento;
            $this->mes_ejecucion = $informe->mes_ejecucion;
            $this->corresponde_texto_periodo = $informe->corresponde_texto_periodo;
            $this->novedad = $informe->novedad ?? '';
            $this->fiducia = $informe->fiducia ?? '';
            $this->infopersonal = $informe->infopersonal ?? '';
            $this->infoaiu = $informe->infoaiu ?? '';
            $this->anexos = $informe->anexos ?? '';
            $this->recomendacion = $informe->recomendacion ?? '';

            $contrato = $informe->contrato;
            $proveedor = $contrato->proveedor ? $contrato->proveedor->nombre : 'N/A';
            $valor = '$' . number_format($contrato->valorTotal, 2, ',', '.');
            $saldo = '$' . number_format($contrato->saldo, 2, ',', '.');
            $this->contratoInfoModal = "Contrato: {$contrato->numcontrato} | Proveedor: {$proveedor} | Valor: {$valor} | Saldo: {$saldo}";

            $obligacionesGuardadas = $informe->informeobligaciones;
            if ($obligacionesGuardadas->count() > 0) {
                $this->obligacionesList = $obligacionesGuardadas->map(fn ($o) => [
                    'id' => $o->id,
                    'numeral' => $o->numeral,
                    'obligacion_deta' => $o->obligacion_deta,
                    'entregable' => $o->entregable,
                    'confirmar' => $o->confirmar,
                ])->toArray();
            } else {
                $this->obligacionesList = $contrato->obligaciones->map(fn ($o) => [
                    'id' => $o->id,
                    'numeral' => $o->numeral,
                    'obligacion_deta' => $o->obligacion_deta,
                    'entregable' => $this->total_info > 0 ? $o->entregable : 'No se requirió en este periodo',
                    'confirmar' => $this->total_info > 0 ? 'SI' : 'NO',
                ])->toArray();
            }

            $riesgosGuardados = $informe->informeriesgos;
            if ($riesgosGuardados->count() > 0) {
                $this->riesgosList = $riesgosGuardados->map(fn ($r) => [
                    'id' => $r->id,
                    'tipo' => $r->tipo,
                    'descripcion' => $r->descripcion,
                    'tratamiento' => $r->tratamiento,
                    'responsable' => $r->responsable,
                    'periodicidad' => $r->periodicidad,
                ])->toArray();
            } else {
                $this->riesgosList = $contrato->riesgos->map(fn ($r) => [
                    'id' => $r->id,
                    'tipo' => $r->tipo,
                    'descripcion' => $r->descripcion,
                    'tratamiento' => $r->tratamiento,
                    'responsable' => $r->responsable,
                    'periodicidad' => $r->periodicidad,
                ])->toArray();
            }

            $this->paso = 2;
        }

        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->paso = 1;
        $this->busquedaContrato = '';
        $this->contratoInfoModal = '';
        $this->obligacionesList = [];
        $this->riesgosList = [];
        $this->errorBusqueda = '';
        $this->reset([
            'cansecu_infor', 'fecha', 'tramite_pago_id', 'estado', 'total_info',
            'saldo_viene', 'porcentaje_cumplimiento', 'mes_ejecucion',
            'corresponde_texto_periodo', 'novedad', 'fiducia', 'infopersonal',
            'infoaiu', 'anexos', 'recomendacion', 'editingId', 'mesesFaltantes',
            'errorMesesFaltantes', 'modalConfirmarMesesFaltantes', 'errorDuplicado'
        ]);
        $this->resetValidation();
    }

    public function abrirEditarObligacion(int $index): void
    {
        $this->editarObligacionIndex = $index;
        $this->editEntregable = $this->obligacionesList[$index]['entregable'] ?? '';
        $this->editConfirmar = $this->obligacionesList[$index]['confirmar'] ?? 'SI';
        $this->modalEditarObligacionOpen = true;
    }

    public function guardarEditarObligacion(): void
    {
        if ($this->editarObligacionIndex >= 0 && isset($this->obligacionesList[$this->editarObligacionIndex])) {
            $this->obligacionesList[$this->editarObligacionIndex]['entregable'] = $this->editEntregable;
            $this->obligacionesList[$this->editarObligacionIndex]['confirmar'] = $this->editConfirmar;
        }
        $this->modalEditarObligacionOpen = false;
        $this->editarObligacionIndex = -1;
        $this->editEntregable = '';
        $this->editConfirmar = 'SI';
    }

    public function closeEditarObligacionModal(): void
    {
        $this->modalEditarObligacionOpen = false;
        $this->editarObligacionIndex = -1;
        $this->editEntregable = '';
        $this->editConfirmar = 'SI';
    }

    public function abrirModalObligacionesMasivo(): void
    {
        $this->nuevoEntregable = '';
        $this->nuevoConfirmar = 'SI';
        $this->modalObligacionesMasivoOpen = true;
    }

    public function aplicarObligacionesMasivo(): void
    {
        foreach ($this->obligacionesList as &$obligacion) {
            $obligacion['entregable'] = $this->nuevoEntregable;
            $obligacion['confirmar'] = $this->nuevoConfirmar;
        }
        unset($obligacion);
        $this->modalObligacionesMasivoOpen = false;
        $this->nuevoEntregable = '';
        $this->nuevoConfirmar = 'SI';
    }

    public function closeObligacionesMasivoModal(): void
    {
        $this->modalObligacionesMasivoOpen = false;
        $this->nuevoEntregable = '';
        $this->nuevoConfirmar = 'SI';
    }

    public function abrirEditarRiesgo(int $index): void
    {
        $this->editarRiesgoIndex = $index;
        $this->editTratamiento = $this->riesgosList[$index]['tratamiento'] ?? '';
        $this->modalEditarRiesgoOpen = true;
    }

    public function guardarEditarRiesgo(): void
    {
        if ($this->editarRiesgoIndex >= 0 && isset($this->riesgosList[$this->editarRiesgoIndex])) {
            $this->riesgosList[$this->editarRiesgoIndex]['tratamiento'] = $this->editTratamiento;
        }
        $this->modalEditarRiesgoOpen = false;
        $this->editarRiesgoIndex = -1;
        $this->editTratamiento = '';
    }

    public function closeEditarRiesgoModal(): void
    {
        $this->modalEditarRiesgoOpen = false;
        $this->editarRiesgoIndex = -1;
        $this->editTratamiento = '';
    }

    public function abrirModalRiesgosMasivo(): void
    {
        $this->nuevoTratamiento = '';
        $this->modalRiesgosMasivoOpen = true;
    }

    public function aplicarRiesgosMasivo(): void
    {
        foreach ($this->riesgosList as &$riesgo) {
            $riesgo['tratamiento'] = $this->nuevoTratamiento;
        }
        unset($riesgo);
        $this->modalRiesgosMasivoOpen = false;
        $this->nuevoTratamiento = '';
    }

    public function closeRiesgosMasivoModal(): void
    {
        $this->modalRiesgosMasivoOpen = false;
        $this->nuevoTratamiento = '';
    }

    public function confirmDelete(Informe $informe): void
    {
        $contrato = Contrato::find($informe->contrato_id);

        if ($informe->cansecu_infor != $contrato->cansecu_infor) {
            session()->flash('error', 'Solo se puede eliminar el último informe creado.');
            return;
        }

        $this->informeToDeleteId = $informe->id;
        $this->informeToDeleteName = $informe->cansecu_infor;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->informeToDeleteId = null;
        $this->informeToDeleteName = '';
    }

    public function delete(): void
    {
        $informe = Informe::findOrFail($this->informeToDeleteId);

        DB::transaction(function () use ($informe) {
            $contrato = Contrato::findOrFail($informe->contrato_id);

            // Retroceder TODOS los pagos que estaban en el consecutivo del informe
            // eliminado o en consecutivos superiores, moviéndolos 1 posición atrás.
            // Así quedan sincronizados con el cansecu_infor que se va a decrementar.
            Pago::where('contrato_id', $informe->contrato_id)
                ->where('cansecu_infor', '>=', $informe->cansecu_infor)
                ->update(['cansecu_infor' => DB::raw('cansecu_infor - 1')]);

            $contrato->update(['cansecu_infor' => $contrato->cansecu_infor - 1]);

            $informe->informeobligaciones()->delete();
            $informe->informeriesgos()->delete();
            $informe->informeregistros()->delete();
            $informe->delete();
        });

        session()->flash('message', 'Informe eliminado correctamente.');
        $this->closeDeleteModal();
    }

    public function imprimirInforme(int $informeId): void
    {
        // TODO: implementar reporte de impresión del informe
        session()->flash('message', 'Función de imprimir próximamente.');
    }

    private function crearSnapshotRegistros(Informe $informe): void
    {
        $registros = Registro::where('contrato_id', $informe->contrato_id)->get();

        foreach ($registros as $registro) {
            InformeRegistro::create([
                'informe_id' => $informe->id,
                'numero_reg' => $registro->numero_reg ?? '',
                'valor_reg' => $registro->valor_reg ?? 0,
                'fecha_reg' => $registro->fecha_reg ?? now(),
                'newplazoejecucion' => $registro->newplazoejecucion ?? now(),
                'tiporegistro_id' => $registro->tiporegistro_id ?? 1,
            ]);
        }
    }

    public function save(): void
    {
        if (!$this->contratoId) {
            $this->mostrarAlerta('error', 'Error', 'No se ha seleccionado un contrato.');
            return;
        }

        if (!$this->editingId) {
            $this->verificarMesesFaltantes();
            if (count($this->mesesFaltantes) > 0) {
                $this->errorMesesFaltantes = 'No se puede crear el informe. Faltan informes para: ' . implode(', ', $this->mesesFaltantes) . '. Debe crearlos primero.';
                return;
            }

            // Verificar que no ya exista un informe para el mismo contrato, mes y año
            $anio = $this->fecha ? date('Y', strtotime($this->fecha)) : date('Y');
            $existe = Informe::where('contrato_id', $this->contratoId)
                ->where('mes_ejecucion', $this->mes_ejecucion)
                ->whereYear('fecha', $anio)
                ->where('estado', '!=', 'anulado')
                ->exists();
            if ($existe) {
                $mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                $nombreMes = $mesesNombres[(int)$this->mes_ejecucion] ?? $this->mes_ejecucion;
                $this->errorDuplicado = 'Ya existe un informe para este contrato en ' . $nombreMes . ' ' . $anio . '.';
                return;
            }
        }
        $this->errorMesesFaltantes = '';
        $this->errorDuplicado = '';

        $this->validate([
            'cansecu_infor' => ['required', 'string', 'max:5'],
            'fecha' => ['required', 'date'],
            'estado' => ['required', 'string', 'max:20'],
            'total_info' => ['required', 'numeric', 'min:0'],
            'saldo_viene' => ['required', 'numeric', 'min:0'],
            'porcentaje_cumplimiento' => ['required', 'string'],
            'mes_ejecucion' => ['required', 'string', 'max:2'],
            'corresponde_texto_periodo' => ['required', 'string'],
        ]);

        $data = [
            'cansecu_infor' => $this->cansecu_infor,
            'fecha' => $this->fecha,
            'contrato_id' => $this->contratoId,
            'tramite_pago_id' => $this->tramite_pago_id ?: null,
            'estado' => $this->estado,
            'total_info' => $this->total_info,
            'saldo_viene' => $this->saldo_viene,
            'porcentaje_cumplimiento' => $this->porcentaje_cumplimiento,
            'mes_ejecucion' => $this->mes_ejecucion,
            'corresponde_texto_periodo' => $this->corresponde_texto_periodo,
            'novedad' => $this->novedad ?: null,
            'fiducia' => $this->fiducia ?: null,
            'infopersonal' => $this->infopersonal ?: null,
            'infoaiu' => $this->infoaiu ?: null,
            'anexos' => $this->anexos ?: null,
            'recomendacion' => $this->recomendacion ?: null,
            'user_id' => auth()->id(),
        ];

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                $informe = Informe::findOrFail($this->editingId);
                $informe->update($data);

                $informe->informeobligaciones()->delete();
                $informe->informeriesgos()->delete();
                $informe->informeregistros()->delete();
            } else {
                $informe = Informe::create($data);

                $contrato = Contrato::findOrFail($this->contratoId);
                $contrato->update(['cansecu_infor' => $contrato->cansecu_infor + 1]);
            }

            $this->crearSnapshotRegistros($informe);

            foreach ($this->obligacionesList as $obligacion) {
                $informe->informeobligaciones()->create([
                    'numeral' => $obligacion['numeral'],
                    'obligacion_deta' => $obligacion['obligacion_deta'],
                    'entregable' => $obligacion['entregable'],
                    'confirmar' => $obligacion['confirmar'],
                    'contrato_id' => $this->contratoId,
                ]);
            }

            foreach ($this->riesgosList as $riesgo) {
                $informe->informeriesgos()->create([
                    'tipo' => $riesgo['tipo'],
                    'descripcion' => $riesgo['descripcion'],
                    'tratamiento' => $riesgo['tratamiento'],
                    'responsable' => $riesgo['responsable'],
                    'periodicidad' => $riesgo['periodicidad'],
                ]);
            }
        });

        session()->flash('message', $this->editingId ? 'Informe actualizado correctamente.' : 'Informe creado correctamente.');
        $this->closeModal();
    }
};
?>

<div>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Informes por Contrato</h1>
        </div>

        <!-- Flash messages -->
        @if (session()->has('message'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-400">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        <!-- Buscar contrato (para filtrar listado) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filtrar por Contrato</label>
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model.live="numcontrato"
                    wire:keydown.enter="buscarContrato"
                    class="w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                    placeholder="Ej: 010-009-2026"
                />
                <button wire:click="buscarContrato" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                    Buscar
                </button>
            </div>
            @error('numcontrato')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Info del contrato encontrado -->
        @if ($contratoEncontrado)
            <div class="mb-6 px-4 py-3 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 dark:bg-violet-900/30 dark:border-violet-700 dark:text-violet-400">
                {{ $contratoInfo }}
            </div>
        @endif

        <!-- Botón nuevo informe -->
        <div class="flex justify-end mb-4">
            <button wire:click="openModal" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                + Nuevo Informe
            </button>
        </div>

        <!-- Tabla de informes -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">N°</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Consecutivo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contrato</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">% Cumplimiento</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pagos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse ($this->informes as $informe)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $informe->cansecu_infor }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $informe->contrato->numcontrato }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $informe->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($informe->estado === 'abierto') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                    @elseif($informe->estado === 'cerrado') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                    @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                    @endif">
                                    {{ ucfirst($informe->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">${{ number_format($informe->total_info, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $informe->porcentaje_cumplimiento }}%</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $pagosInforme = \App\Models\Pago::where('contrato_id', $informe->contrato_id)
                                        ->where('cansecu_infor', $informe->cansecu_infor)
                                        ->where('estado', 'cerrado')
                                        ->get();
                                @endphp
                                @if ($pagosInforme->count() > 0)
                                    <button wire:click="verPagosInforme({{ $informe->id }})" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        {{ $pagosInforme->count() }} pago(s)
                                    </button>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-xs">Sin pagos</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <button wire:click="openModal({{ $informe->id }})" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 mr-2" title="Editar">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button onclick="window.open('{{ url('informes/imprimir/' . $informe->id) }}', '_blank')" class="text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 mr-2" title="Imprimir">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </button>
                                <button onclick="window.open('{{ url('informes/imprimircomedor/' . $informe->id) }}', '_blank')" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 mr-2" title="Imprimir Comedores">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </button>
                                <button wire:click="confirmDelete({{ $informe->id }})" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                @if ($contratoEncontrado)
                                    No hay informes registrados para este contrato.
                                @else
                                    Ingrese un número de contrato para ver los informes.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        @if ($this->informes->hasPages())
            <div class="mt-4">
                {{ $this->informes->links() }}
            </div>
        @endif
    </div>

    <!-- Modal CREAR/EDITAR -->
    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeModal" wire:key="informe-modal">
            <div class="w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[90vh] overflow-y-auto" wire:click.stop>

                <!-- PASO 1: Buscar contrato -->
                @if ($paso === 1)
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Nuevo Informe - Buscar Contrato</h3>

                    @if ($errorBusqueda)
                        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400 text-sm">
                            {{ $errorBusqueda }}
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Contrato <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model.live="busquedaContrato"
                                wire:keydown.enter="buscarContratoModal"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ej: 010-009-2026"
                            />
                            <button wire:click="buscarContratoModal" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition">
                                Buscar
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                            Cancelar
                        </button>
                    </div>

                <!-- PASO 2: Formulario del informe -->
                @else
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                            {{ $editingId ? 'Editar Informe' : 'Nuevo Informe' }}
                            <span class="ml-2 text-sm font-normal text-violet-600 dark:text-violet-400">N° {{ $cansecu_infor }}</span>
                        </h3>
                        @unless ($editingId)
                            <button wire:click="volverPaso1" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400">
                                ← Cambiar contrato
                            </button>
                        @endunless
                    </div>

                    <!-- Info del contrato -->
                    <div class="mb-4 px-4 py-3 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 dark:bg-violet-900/30 dark:border-violet-700 dark:text-violet-400 text-sm">
                        {{ $contratoInfoModal }}
                    </div>

                    <!-- Error dentro del modal: meses faltantes -->
                    @if ($errorMesesFaltantes)
                        <div id="errorInforme" class="sticky top-0 z-10 mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400 text-sm shadow-md">
                            {{ $errorMesesFaltantes }}
                            <button wire:click="crearMesesFaltantes" type="button" class="ml-3 px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-lg transition">
                                Crear informes faltantes
                            </button>
                        </div>
                    @endif

                    <!-- Error dentro del modal: informe duplicado -->
                    @if ($errorDuplicado)
                        <div id="errorInforme" class="sticky top-0 z-10 mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400 text-sm shadow-md">
                            {{ $errorDuplicado }}
                        </div>
                    @endif

                    <!-- Resumen de pagos -->
                    @php $agrupados = $this->pagosAgrupados; @endphp
                    @if ($agrupados->count() > 0)
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Pagos cerrados para informe N° {{ $contratoEncontrado ? (\App\Models\Contrato::find($this->contratoId)->cansecu_infor + 1) : '' }} ({{ $agrupados->count() }})</h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">N° Pago</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Fecha</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Valor Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                        @foreach ($agrupados as $pago)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $pago->numero }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $pago->fecha->format('d/m/Y') }}</td>
                                                <td class="px-3 py-2 text-sm text-right font-semibold text-gray-800 dark:text-gray-200">${{ number_format($pago->valor_total, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50 dark:bg-gray-700/50">
                                            <td colspan="2" class="px-3 py-2 text-sm font-bold text-right text-gray-800 dark:text-gray-200">Total:</td>
                                            <td class="px-3 py-2 text-sm text-right font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($agrupados->sum('valor_total'), 2, ',', '.') }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-400 text-sm">
                            No hay pagos cerrados pendientes para este informe.
                        </div>
                    @endif

                    <!-- Formulario -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Corresponde texto período -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correspondiente a <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                wire:model="corresponde_texto_periodo"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ej: Enero 2026"
                            />
                            @error('corresponde_texto_periodo')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fecha -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha <span class="text-red-500">*</span></label>
                            <input
                                type="date"
                                wire:model="fecha"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            />
                            @error('fecha')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado <span class="text-red-500">*</span></label>
                            <select
                                wire:model="estado"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            >
                                <option value="abierto">Abierto</option>
                                <option value="cerrado">Cerrado</option>
                                <option value="anulado">Anulado</option>
                            </select>
                            @error('estado')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Trámite de pago -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trámite de Pago</label>
                            <select
                                wire:model="tramite_pago_id"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            >
                                <option value="">Ninguno</option>
                                @foreach ($this->tramites as $tramite)
                                    <option value="{{ $tramite->id }}">{{ $tramite->numero }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Total informe (calculado automáticamente) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total Informe</label>
                            <input
                                type="text"
                                value="${{ number_format($total_info, 2, ',', '.') }}"
                                readonly
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 cursor-not-allowed"
                            />
                        </div>

                        <!-- % Cumplimiento -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">% Cumplimiento</label>
                            <input
                                type="text"
                                value="{{ $porcentaje_cumplimiento }}%"
                                readonly
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 cursor-not-allowed"
                            />
                            @error('porcentaje_cumplimiento')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Mes ejecución -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mes Ejecución <span class="text-red-500">*</span></label>
                            <select
                                wire:model="mes_ejecucion"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            >
                                <option value="">Seleccione</option>
                                @php
                                    $meses = [
                                        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                                        '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                                        '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                                        '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
                                    ];
                                @endphp
                                @foreach($meses as $num => $nombre)
                                    <option value="{{ $num }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                            @error('mes_ejecucion')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                            @if (count($mesesFaltantes) > 0)
                                <div class="mt-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-xs">
                                    <strong>Faltan informes para:</strong>
                                    {{ implode(', ', $mesesFaltantes) }}.
                                    Debe crearlos primero.
                                </div>
                            @endif
                        </div>

                        <!-- Novedad -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Novedad</label>
                            <textarea
                                wire:model="novedad"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese novedades"
                            ></textarea>
                        </div>

                        <!-- Fiducia -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fiducia</label>
                            <textarea
                                wire:model="fiducia"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese información de fiducia"
                            ></textarea>
                        </div>

                        <!-- Info Personal -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Info Personal</label>
                            <textarea
                                wire:model="infopersonal"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese información personal"
                            ></textarea>
                        </div>

                        <!-- Info AIU -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Info AIU</label>
                            <textarea
                                wire:model="infoaiu"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese información AIU"
                            ></textarea>
                        </div>

                        <!-- Anexos -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anexos</label>
                            <textarea
                                wire:model="anexos"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese anexos"
                            ></textarea>
                        </div>

                        <!-- Recomendación -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recomendación</label>
                            <textarea
                                wire:model="recomendacion"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                                placeholder="Ingrese recomendación"
                            ></textarea>
                        </div>

                        <!-- Obligaciones del contrato -->
                        @if (count($this->obligacionesList) > 0)
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Obligaciones del Contrato ({{ count($this->obligacionesList) }})</h4>
                                    <button wire:click="abrirModalObligacionesMasivo" type="button" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-lg transition">
                                        Actualizar Todas
                                    </button>
                                </div>
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">N°</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Numeral</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Obligación</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Entregable</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Confirmar</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach ($this->obligacionesList as $index => $obligacion)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                    <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $index + 1 }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion['numeral'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion['obligacion_deta'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $obligacion['entregable'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-center font-semibold {{ $obligacion['confirmar'] === 'SI' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ $obligacion['confirmar'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-center">
                                                        <button wire:click="abrirEditarObligacion({{ $index }})" type="button" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300" title="Editar">
                                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <!-- Riesgos del contrato -->
                        @if (count($this->riesgosList) > 0)
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Riesgos del Contrato ({{ count($this->riesgosList) }})</h4>
                                    <button wire:click="abrirModalRiesgosMasivo" type="button" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium rounded-lg transition">
                                        Actualizar Todos
                                    </button>
                                </div>
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">N°</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Tipo</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Descripción</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Tratamiento</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Responsable</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Periodicidad</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach ($this->riesgosList as $index => $riesgo)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                    <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $index + 1 }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo['tipo'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo['descripcion'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo['tratamiento'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo['responsable'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $riesgo['periodicidad'] }}</td>
                                                    <td class="px-3 py-2 text-sm text-center">
                                                        <button wire:click="abrirEditarRiesgo({{ $index }})" type="button" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300" title="Editar">
                                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                            Cancelar
                        </button>
                        <button wire:click="save" {{ count($mesesFaltantes) > 0 ? 'disabled' : '' }} class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition {{ count($mesesFaltantes) > 0 ? 'opacity-50 cursor-not-allowed' : '' }}">
                            {{ $editingId ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Modal EDITAR OBLIGACIÓN (individual) -->
    @if ($modalEditarObligacionOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeEditarObligacionModal" wire:key="editar-obligacion-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Editar Obligación</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entregable</label>
                        <textarea
                            wire:model="editEntregable"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese entregable"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar</label>
                        <select
                            wire:model="editConfirmar"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                        >
                            <option value="SI">SI</option>
                            <option value="NO">NO</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeEditarObligacionModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="guardarEditarObligacion" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal ACTUALIZAR TODAS (masivo) -->
    @if ($modalObligacionesMasivoOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeObligacionesMasivoModal" wire:key="obligaciones-masivo-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Actualizar Todas las Obligaciones</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Lo que ingrese reemplazará el valor en TODAS las filas.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entregable</label>
                        <textarea
                            wire:model="nuevoEntregable"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese entregable"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar</label>
                        <select
                            wire:model="nuevoConfirmar"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                        >
                            <option value="SI">SI</option>
                            <option value="NO">NO</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeObligacionesMasivoModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="aplicarObligacionesMasivo" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition">
                        Aplicar a Todas
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal EDITAR RIESGO (individual) -->
    @if ($modalEditarRiesgoOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeEditarRiesgoModal" wire:key="editar-riesgo-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Editar Riesgo</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tratamiento</label>
                        <textarea
                            wire:model="editTratamiento"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese tratamiento"
                        ></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeEditarRiesgoModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="guardarEditarRiesgo" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition">
                        Aplicar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal ACTUALIZAR TODOS (masivo riesgos) -->
    @if ($modalRiesgosMasivoOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeRiesgosMasivoModal" wire:key="riesgos-masivo-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Actualizar Todos los Riesgos</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Lo que ingrese reemplazará el valor de Tratamiento en TODAS las filas.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tratamiento</label>
                        <textarea
                            wire:model="nuevoTratamiento"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 focus:border-violet-500 focus:ring-violet-500"
                            placeholder="Ingrese tratamiento"
                        ></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="closeRiesgosMasivoModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="aplicarRiesgosMasivo" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition">
                        Aplicar a Todos
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal ELIMINAR -->
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="closeDeleteModal" wire:key="delete-modal">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 text-center">Eliminar Informe</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mt-2 mb-6">
                    ¿Está seguro que desea eliminar el informe <strong class="text-gray-800 dark:text-gray-200">{{ $informeToDeleteName }}</strong>? Esta acción no se puede deshacer.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeDeleteModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="delete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal PAGOS DEL INFORME -->
    @if ($modalPagosOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60" wire:click="cerrarModalPagos" wire:key="pagos-modal">
            <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 max-h-[80vh] overflow-y-auto" wire:click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Pagos del Informe N° {{ $modalPagosInforme }}</h3>
                    <button wire:click="cerrarModalPagos" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @if (count($modalPagosLista) > 0)
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">N° Pago</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Fecha</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                @foreach ($modalPagosLista as $pago)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $pago['numero'] }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($pago['fecha'])->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2 text-sm text-right font-semibold text-gray-800 dark:text-gray-200">${{ number_format($pago['valor_total'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-gray-700/50">
                                    <td colspan="3" class="px-3 py-2 text-sm font-bold text-right text-gray-800 dark:text-gray-200">Total:</td>
                                    <td class="px-3 py-2 text-sm text-right font-bold text-emerald-600 dark:text-emerald-400">${{ number_format(collect($modalPagosLista)->sum('valor_total'), 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No hay pagos cerrados para este informe.</p>
                @endif
                <div class="flex justify-end mt-4">
                    <button wire:click="cerrarModalPagos" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal CONFIRMAR MESES FALTANTES -->
    @if ($modalConfirmarMesesFaltantes)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60" wire:click="closeModalConfirmarMeses">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 text-center">Crear informes faltantes</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mt-2 mb-4">
                    Se crearán <strong>{{ count($mesesFaltantes) }}</strong> informe(s) con valor $0:
                </p>
                <ul class="mb-4 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                    @foreach ($mesesFaltantes as $mes)
                        <li>{{ $mes }}</li>
                    @endforeach
                </ul>
                <div class="flex justify-end gap-3">
                    <button wire:click="closeModalConfirmarMeses" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 transition">
                        Cancelar
                    </button>
                    <button wire:click="confirmarCrearMesesFaltantes" wire:loading.attr="disabled" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmarCrearMesesFaltantes">Crear y continuar</span>
                        <span wire:loading wire:target="confirmarCrearMesesFaltantes">Creando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de ALERTA global -->
    @if ($modalAlerta)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/60" wire:click="cerrarAlerta">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" wire:click.stop>
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full
                    {{ $modalAlertaTipo === 'error' ? 'bg-red-100 dark:bg-red-900/30' : ($modalAlertaTipo === 'exito' ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-amber-100 dark:bg-amber-900/30') }}">
                    @if ($modalAlertaTipo === 'error')
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @elseif ($modalAlertaTipo === 'exito')
                        <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 text-center">{{ $modalAlertaTitulo }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center mt-2 mb-4">{{ $modalAlertaMensaje }}</p>
                <div class="flex justify-center">
                    <button wire:click="cerrarAlerta" class="px-6 py-2 text-sm font-medium text-white rounded-lg transition
                        {{ $modalAlertaTipo === 'error' ? 'bg-red-600 hover:bg-red-700' : ($modalAlertaTipo === 'exito' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-500 hover:bg-amber-600') }}">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
