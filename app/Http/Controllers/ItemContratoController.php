<?php

namespace App\Http\Controllers;

use App\Exports\ItemContratoPlantillaExport;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ItemContratoController extends Controller
{
    public function plantillaExcel(int $contratoId, int $rubroId)
    {
        $productos = Producto::where('rubro_id', $rubroId)
            ->where('regional_id', Auth::user()->regional_id)
            ->with('uso')
            ->orderBy('id')
            ->get();

        $nombreRubro = \App\Models\Rubro::find($rubroId)?->nombre_rubro ?? 'Rubro';
        $numContrato = \App\Models\Contrato::find($contratoId)?->numcontrato ?? 'Contrato';

        $filename = "asignacion_producto_{$numContrato}.xlsx";

        return Excel::download(
            new ItemContratoPlantillaExport($productos),
            $filename
        );
    }
}
