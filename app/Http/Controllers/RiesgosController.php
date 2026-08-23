<?php

namespace App\Http\Controllers;

use App\Exports\RiesgosPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;

class RiesgosController extends Controller
{
    public function plantillaExcel()
    {
        return Excel::download(
            new RiesgosPlantillaExport,
            'plantilla_riesgos.xlsx'
        );
    }
}
