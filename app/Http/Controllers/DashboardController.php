<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Movirubro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $regionalId = $user->regional_id;

        $data = [];

        // 1. KPI Cards
        $data['totalContratos'] = $this->totalContratos($isAdmin, $regionalId);
        $data['totalFacturado'] = $this->totalFacturado($isAdmin, $regionalId);
        $data['totalPagado'] = $this->totalPagado($isAdmin, $regionalId);
        $data['saldoDisponible'] = $this->saldoDisponible($isAdmin, $regionalId);

        // 2. Facturación vs Pagos mensual (últimos 12 meses)
        $data['facturacionMensual'] = $this->facturacionMensual($isAdmin, $regionalId);
        $data['pagosMensual'] = $this->pagosMensual($isAdmin, $regionalId);
        $data['mesesLabels'] = $this->mesesLabels();

        // 3. Facturas por estado
        $data['facturasPorEstado'] = $this->facturasPorEstado($isAdmin, $regionalId);

        // 4. Pagos por estado
        $data['pagosPorEstado'] = $this->pagosPorEstado($isAdmin, $regionalId);

        // 5. Retenciones aplicadas
        $data['retenciones'] = $this->retenciones($isAdmin, $regionalId);

        // 6. Ejecución presupuestal
        $data['presupuesto'] = $this->presupuesto($isAdmin, $regionalId);

        return view('pages/dashboard/dashboard', ['data' => $data]);
    }

    private function applyRegionalFilter($query, $isAdmin, $regionalId)
    {
        if (!$isAdmin && $regionalId) {
            $query->whereHas('user', fn($q) => $q->where('regional_id', $regionalId));
        }
        return $query;
    }

    private function applyRegionalFilterFactura($query, $isAdmin, $regionalId)
    {
        if (!$isAdmin && $regionalId) {
            $query->whereHas('contrato.user', fn($q) => $q->where('regional_id', $regionalId));
        }
        return $query;
    }

    private function totalContratos($isAdmin, $regionalId)
    {
        $query = Contrato::query();
        $this->applyRegionalFilter($query, $isAdmin, $regionalId);
        return $query->count();
    }

    private function totalFacturado($isAdmin, $regionalId)
    {
        $query = Factura::where('estado', '!=', 'anulada');
        $this->applyRegionalFilterFactura($query, $isAdmin, $regionalId);
        return $query->sum(DB::raw('subtotal + total_iva'));
    }

    private function totalPagado($isAdmin, $regionalId)
    {
        $query = Pago::where('estado', 'cerrado');
        $this->applyRegionalFilter($query, $isAdmin, $regionalId);
        return $query->sum('valor_total');
    }

    private function saldoDisponible($isAdmin, $regionalId)
    {
        $query = Movirubro::query();
        if (!$isAdmin && $regionalId) {
            $query->whereHas('registro.contrato.user', fn($q) => $q->where('regional_id', $regionalId));
        }
        return $query->sum('saldo_rubro');
    }

    private function mesesLabels()
    {
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');
        }
        return $labels;
    }

    private function facturacionMensual($isAdmin, $regionalId)
    {
        $query = Factura::where('estado', '!=', 'anulada')
            ->where('fecha', '>=', now()->subMonths(12)->startOfMonth());
        $this->applyRegionalFilterFactura($query, $isAdmin, $regionalId);

        $results = $query->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(subtotal + total_iva) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $data[] = $results[$key] ?? 0;
        }
        return $data;
    }

    private function pagosMensual($isAdmin, $regionalId)
    {
        $query = Pago::where('estado', 'cerrado')
            ->where('fecha', '>=', now()->subMonths(12)->startOfMonth());
        $this->applyRegionalFilter($query, $isAdmin, $regionalId);

        $results = $query->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(valor_total) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $data[] = $results[$key] ?? 0;
        }
        return $data;
    }

    private function facturasPorEstado($isAdmin, $regionalId)
    {
        $query = Factura::query();
        $this->applyRegionalFilterFactura($query, $isAdmin, $regionalId);

        $results = $query->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'Borrador' => $results['borrador'] ?? 0,
            'Emitida' => $results['emitida'] ?? 0,
            'Pagada' => $results['pagada'] ?? 0,
            'Anulada' => $results['anulada'] ?? 0,
        ];
    }

    private function pagosPorEstado($isAdmin, $regionalId)
    {
        $query = Pago::query();
        $this->applyRegionalFilter($query, $isAdmin, $regionalId);

        $results = $query->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'Abierto' => $results['abierto'] ?? 0,
            'Cerrado' => $results['cerrado'] ?? 0,
            'Anulada' => $results['anulada'] ?? 0,
        ];
    }

    private function retenciones($isAdmin, $regionalId)
    {
        $query = DB::table('factura_linea_retenciones')
            ->join('factura_lineas', 'factura_lineas.id', '=', 'factura_linea_retenciones.factura_linea_id')
            ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
            ->join('retenciones', 'retenciones.id', '=', 'factura_linea_retenciones.retencion_id')
            ->where('facturas.estado', '!=', 'anulada');

        if (!$isAdmin && $regionalId) {
            $query->join('contratos', 'facturas.contrato_id', '=', 'contratos.id')
                  ->join('users', 'contratos.user_id', '=', 'users.id')
                  ->where('users.regional_id', $regionalId);
        }

        $results = $query->select('retenciones.name as nombre', DB::raw('SUM(valor_retenido) as total'))
            ->groupBy('retenciones.name')
            ->pluck('total', 'nombre')
            ->toArray();

        return [
            'Retefuente' => $results['Retefuente'] ?? 0,
            'Reteiva' => $results['Reteiva'] ?? 0,
            'Reteica' => $results['Reteica'] ?? 0,
            'Fedepapa' => $results['Fedepapa'] ?? 0,
            'Asohofrucol' => $results['Asohofrucol'] ?? 0,
            'Estampilla' => $results['Estampilla Magdalena'] ?? 0,
        ];
    }

    private function presupuesto($isAdmin, $regionalId)
    {
        $query = Movirubro::query();
        if (!$isAdmin && $regionalId) {
            $query->whereHas('registro.contrato.user', fn($q) => $q->where('regional_id', $regionalId));
        }

        $result = $query->selectRaw('SUM(valor_rubro) as valor_total, SUM(saldo_rubro) as saldo')
            ->first();

        return [
            'valor_total' => $result->valor_total ?? 0,
            'saldo' => $result->saldo ?? 0,
            'ejecutado' => ($result->valor_total ?? 0) - ($result->saldo ?? 0),
        ];
    }
}
