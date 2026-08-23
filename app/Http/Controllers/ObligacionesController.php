<?php

namespace App\Http\Controllers;

use App\Exports\ObligacionesPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;

class ObligacionesController extends Controller
{
    public function plantillaExcel()
    {
        return Excel::download(
            new ObligacionesPlantillaExport,
            'plantilla_obligaciones.xlsx'
        );
    }
}
