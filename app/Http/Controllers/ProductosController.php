<?php

namespace App\Http\Controllers;

use App\Exports\ProductosPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;

class ProductosController extends Controller
{
    public function plantillaExcel()
    {
        return Excel::download(
            new ProductosPlantillaExport,
            'plantilla_productos.xlsx'
        );
    }
}
