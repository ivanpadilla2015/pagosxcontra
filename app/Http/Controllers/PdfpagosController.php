<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use App\Models\Pago;
use App\Models\Informe;
use App\Models\Acta;

class PdfpagosController extends Controller
{
    public function imprimepdfxuso(Request $request)
    {
        $data = Pago::findOrFail($request->id);

        $tcontratosinreduc = $data->registrosSnapshot()->where('tiporegistro_id','<', 3)->sum('valor_reg');
        $tredu = $data->registrosSnapshot()->where('tiporegistro_id','=', 3)->sum('valor_reg');
        if ($tredu > 0) {
            $tcontrato = $tcontratosinreduc - $tredu;
        }else{
            $tcontrato = $tcontratosinreduc;
        }

        $tsaldocontra = $data->rubrosSnapshot()->sum('saldo_rubro');
        $ejecutado = $tcontrato -  $tsaldocontra;
        $ultreg = $data->registrosSnapshot()->latest('id')->first();

        $datoUso = DB::table('detalle_pagos')
                    ->join('usos','usos.id', '=', 'detalle_pagos.uso_id')
                    ->join('movirubros','movirubros.id', '=', 'detalle_pagos.movirubro_id')
                    ->join('facturas','facturas.id', '=', 'detalle_pagos.factura_id')
                    ->select(
                        'usos.nombre_uso',
                        'usos.codigo_uso',
                        DB::raw('any_value(facturas.numero) as numfac'),
                        DB::raw('sum(facturas.subtotal+facturas.total_iva) as total_fac'),
                        DB::raw('any_value(movirubros.saldo_rubro) as saldo_rubro')
                    )
                    ->groupBy('detalle_pagos.uso_id')
                    ->where('pago_id', $request->id)
                    ->get();

        $regpres = '';
        $dregs = '';
        $datoreg = DB::table('pagos')
                    ->join('pagodeterubros','pagodeterubros.pago_id', '=', 'pagos.id')
                    ->join('movirubros','movirubros.id', '=', 'pagodeterubros.movirubro_id')
                    ->join('registros','registros.id', '=', 'movirubros.registro_id')
                    ->select(DB::raw('sum(movirubros.saldo_rubro) as total_rubro'), DB::raw('any_value(registros.valor_reg) as valor_reg'), DB::raw('any_value(registros.numero_reg) as numero_reg'), DB::raw('any_value(registros.fecha_reg) as fecha_reg'))
                    ->groupBy('movirubros.registro_id')
                    ->where('pagos.id', $request->id)
                    ->get();

        $numreg = '';
        $ferg= '';
        $vareg= '';
        if ($data->registrosSnapshot()->count() == 1) {
            $fe = new DateTime($ultreg->registro->fecha_reg);
            $fe = $fe->format('d/m/Y');
            $regpres= $ultreg->registro->numero_reg. ' de '.$fe;
            $numreg = $ultreg->registro->numero_reg;
            $ferg= $fe;
            $vareg= $data->registrosSnapshot()->sum('valor_reg');
        }else {
            foreach ($data->registrosSnapshot() as $value) {
                $fe = new DateTime($value->registro->fecha_reg);
                $fe = $fe->format('d/m/Y');
                $regpres .= $value->registro->numero_reg. ' / '.$fe.', ';
            }
        }

        $pdf = Pdf::loadView('pdf.pdf_pagos', compact('data','ultreg','tcontrato','tsaldocontra',
                             'ejecutado', 'regpres','datoUso','datoreg','numreg','ferg','vareg'));
        return $pdf->setPaper('letter')->stream();
    }

    public function imprimepdfinfo(Request $request)
    {
        $data = Informe::findOrFail($request->id);
        $ult = $data->informeregistros()->latest('created_at')->first();
        $primer = $data->informeregistros()->first();
        $tcontrato = $data->informeregistros()->sum('valor_reg');

        $tcontratosinreduc = $data->informeregistros()->where('tiporegistro_id','<', 3)->sum('valor_reg');
        $tredu = $data->informeregistros()->where('tiporegistro_id','=', 3)->sum('valor_reg');
        if ($tredu > 0) {
            $tcontrato = $tcontratosinreduc - $tredu;
        }else{
            $tcontrato = $tcontratosinreduc;
        }

        $datofac = DB::table('facturas')
                    ->select('facturas.numero as numfac', DB::raw('any_value(facturas.fecha) as fechafac'), DB::raw('SUM(facturas.subtotal+facturas.total_iva) as valorfac'))
                    ->groupBy('facturas.numero')
                    ->where('facturas.contrato_id', $data->contrato_id)->get();

        $datoUso = DB::table('facturas')
                    ->join('detalle_pagos', 'detalle_pagos.factura_id', '=', 'facturas.id')
                    ->join('usos', 'usos.id', '=', 'detalle_pagos.uso_id')
                    ->select('usos.nombre_uso', 'usos.codigo_uso', DB::raw('sum(detalle_pagos.valor_pagado) as total_fac'))
                    ->groupBy('usos.id', 'usos.nombre_uso', 'usos.codigo_uso')
                    ->where('facturas.contrato_id', $data->contrato_id)
                    ->get();
       

       /* $datoinfobligas = DB::table('informeobligacions')
                    ->join('informes', 'informes.id', '=', 'informeobligacions.informe_id')
                    ->select('informeobligacions.*','informes.mes_ejecucion')
                    ->where('informes.contrato_id', $data->contrato_id)->get(); */

        $pdf = PDF::loadView('pdf.pdf_informe', compact('data', 'datofac', 'ult', 'tcontrato', 'datoUso', 'primer'));
        $pdf->setPaper('letter');
        return $pdf->stream();
    }

    public function imprimepdfinfocomedor(Request $request)
    {
        $data = Informe::findOrFail($request->id);
        $ult = $data->informeregistros()->latest('created_at')->first();
        $primer = $data->informeregistros()->first();
        $tcontrato = $data->informeregistros()->sum('valor_reg');

        $tcontratosinreduc = $data->informeregistros()->where('tiporegistro_id','<', 3)->sum('valor_reg');
        $tredu = $data->informeregistros()->where('tiporegistro_id','=', 3)->sum('valor_reg');
        if ($tredu > 0) {
            $tcontrato = $tcontratosinreduc - $tredu;
        }else{
            $tcontrato = $tcontratosinreduc;
        }

        $datofac = DB::table('detalle_pagos')
                    ->join('facturas', 'facturas.id', '=', 'detalle_pagos.factura_id')
                    ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
                    ->select('facturas.numero as numfac', DB::raw('any_value(facturas.fecha) as fechafac'), DB::raw('SUM(detalle_pagos.valor_pagado) as valorfac'))
                    ->groupBy('facturas.numero')
                    ->where('facturas.contrato_id', $data->contrato_id)
                    ->where('pagos.cansecu_infor', $data->cansecu_infor)
                    ->get(); 
       
        
        $datoUso  = DB::table('facturas')
                    ->join('detalle_pagos', 'detalle_pagos.factura_id', '=', 'facturas.id')
                    ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
                    ->join('usos', 'usos.id', '=', 'detalle_pagos.uso_id')
                    ->select('usos.nombre_uso', 'usos.codigo_uso', DB::raw('sum(detalle_pagos.valor_pagado) as total_fac'))
                    ->groupBy('usos.id', 'usos.nombre_uso', 'usos.codigo_uso')
                    ->where('facturas.contrato_id', $data->contrato_id)
                    ->where('pagos.cansecu_infor', $data->cansecu_infor)
                    ->get();
        
        $pdf = PDF::loadView('pdf.pdf_informe_comedores', compact('data', 'datofac', 'ult', 'tcontrato', 'datoUso', 'primer'));
        $pdf->setPaper('letter');
        return $pdf->stream();
    }

    public function imprimirActas2(Request $request)
    {
        $acta = Acta::with(['factura.lineas.itemcontrato.producto', 'contrato.proveedor', 'dependencia.municipio', 'user'])
            ->findOrFail($request->id);

        $pdf = PDF::loadView('pdf.pdf_acta', compact('acta'));
        $pdf->setPaper('letter');
        return $pdf->stream();
    }

    public function busqueda(Request $request)
    { //busqueda de las facturas de los pagos que pernecen al un tramite determinado?
        $facturas = Factura::whereIn('id', function ($query) use ($contratoId, $numTramite) { //Busca facturas cuyo id esté dentro del resultado de una subconsulta interna.
            $query->select('factura_id') //Primera subconsulta: selecciona el factura_id de la tabla detalle_pagos.
                ->from('detalle_pagos')
                ->whereIn('pago_id', function ($q2) use ($contratoId, $numTramite) { //Pero solo me interesan los pago_id que estén dentro de otra subconsulta más interna.
                    $q2->select('id') //Segunda subconsulta: selecciona el id de la tabla pagos donde el contrato sea el que busco Y el consecutivo de trámite coincida. Esto me da los IDs de los pagos que pertenecen a ese trámite.
                       ->from('pagos')
                       ->where('contrato_id', $contratoId)
               ->where('cansecu_tramite', $numTramite);
        });
    })->get();
    //o esto tambien
    $facturas = Factura::query() //Inicia una consulta sobre la tabla facturas.
    ->join('detalle_pagos', 'detalle_pagos.factura_id', '=', 'facturas.id')
    ->join('pagos', 'pagos.id', '=', 'detalle_pagos.pago_id')
    ->where('pagos.contrato_id', $contratoId)
    ->where('pagos.cansecu_tramite', $numTramite)
    ->distinct() //Evita duplicados, ya que una factura podría tener múltiples líneas en detalle_pagos.
    ->get(); //Ejecuta la consulta y devuelve los resultados.

     //En resumen: trae las facturas que están asociadas a pagos que pertenecen a un trámite específico de un contrato determinado.
    

    }

    public function imprimirActas(Request $request)
    {
        $act = Acta::with(['factura.lineas.itemcontrato.producto', 'contrato.proveedor', 'dependencia.municipio', 'user'])
            ->findOrFail($request->id);
        $ultreg = $act->contrato->registros()->latest('id')->first();
        $fechaini = $act->contrato->fecha_inicio_contrato;
        $fechafin = $ultreg->newplazoejecucion;
        $f1 = new DateTime($fechaini);
        $f2 = new DateTime($fechafin);
        $cant_meses = $f2->diff($f1);
        $cant_meses = $cant_meses->format('%m');
        $pdf = PDF::loadView('pdf.pdf_acta2023', compact('act','ultreg','cant_meses'));
        $pdf->setPaper('letter');
        return $pdf->stream();
    }

/*

    $act = Acta::findOrFail($request->id);
        $data = Contrato::findOrFail($act->contrato_id);
        $ultreg = $data->registros()->latest('id')->first(); //para conseguir el ultimo
        $fechainicio = $data->fecha_inicio_contrato;

        $fechaini = $act->contrato->fechacontrato;
        $fechafin = $ultreg->newplazoejecucion;
        $f1 = new DateTime($fechaini);
        $f2 = new DateTime($fechafin);
        $cant_meses = $f2->diff($f1);
        $cant_meses = $cant_meses->format('%m');
        //dd($cant_meses);

        $pdf= PDF::loadView('livewire.contrato.pdf_acta2023', compact('act','ultreg','cant_meses','fechainicio'));
        $pdf->setPaper('letter');
        return $pdf->stream();
*/



}
