<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="{{ public_path('css/bootstrap.min.css')}}" >
    <link rel="stylesheet" href="{{ public_path('css/stylos.css')}}">
    
    <title>Acta Recibo Satisfaccion </title>
   </head>
  <body>
    <div>
        <img src="{{ public_path('img/CT-FO-01_2.PNG')}}" class="img-fluid w-100" alt="Responsive image ">
    </div>
    <table >
      <tbody class="coluyy2">
         <tr >
              <td class="coluyy2" colspan="5" style="text-align: left;"><strong>No. DE CONTRATO: </strong>{{ "  ".$act->contrato->numcontrato}} </td>
              <td class="coluyy2" colspan="5" style="text-align: left;"><strong>FECHA DEL CONTRATO:</strong>{{ "  ".$act->contrato->fechacontrato->format('d/m/Y')}} </td>
         </tr>
         <tr >
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>OBJETO CONTRATO: </strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ "  ".$act->contrato->objetocontrato}} </td>
         </tr>
         <tr >
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>CONTRATISTA: </strong></td>
           <td class="coluyy2" colspan="7" style="text-align: left;">{{ "  ".$act->contrato->proveedor->nombre}} </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>NIT/CC/CE:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ "  ".$act->contrato->proveedor->nit}} </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>REPRESENTANATE LEGAL:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ "  ".$act->contrato->proveedor->representante_legal }} </td>
         </tr>
          <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>INTERVENTOR:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ "" }} </td>
         </tr>
          <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>SUPERVISOR:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{"  ".$act->user->name }} </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>FECHA ACTA DE COORDINACIÓN O DE INICIO:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ " ".$act->contrato->fecha_inicio_contrato->format('d/m/Y') }} </td>
         </tr>
          <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>PLAZO DE EJECUCIÓN:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ $cant_meses." Meses" }} </td>
         </tr>
          <tr class="">
          <td class="coluyy2" colspan="3" style="text-align: left;"><strong>FECHA DE TERMINACIÓN CONTRACTUAL:</strong> </td>
          <td class="coluyy2" colspan="7" style="text-align: left;">{{ " ".$ultreg->newplazoejecucion->format('d/m/Y') }} </td>
         </tr>
         <tr >
          <td class="colacta" colspan="10" style="text-align: center;">DATOS DE LA ENTREGA PARCIAL O TOTAL DEL BIEN O SERVICIO </td>
         </tr>
         <tr >
          <td class="coluyy2" colspan="10" style="text-align: center;">TRATA DE LA ENTREGA(parcial/ total) QUE HACE EL CONTRATISTA A LA AGENCIA LOGISTICA DE LAS FUERZAS MILITARES </td>
         </tr>
         <tr >
          <td class="coluyy2" colspan="2" style="text-align: left;">FECHA: {{ $act->factura->fecha_migo->format('d/m/Y') }}</td>
          <td class="coluyy2" colspan="2" style="text-align: left;">HORA: {{ $act->hora}}  </td>
          <td class="coluyy2" colspan="3" style="text-align: left;">{{'ENTREGA PARCIAL:'}} &nbsp;{{'SI'}}&nbsp;x&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;No&nbsp; </td>
          <td class="coluyy2" colspan="3" style="text-align: left;">ENTREGA TOTAL:&nbsp;&nbsp;&nbsp;&nbsp;NO </td>
         </tr>
         <tr>
            <td class="coluyy2" colspan="5" style="text-align: left;">CIUDAD/MUNICIPIO: {{ $act->dependencia->municipio->nombre}}</td>
            <td class="coluyy2" colspan="5" style="text-align: left;">DIRECCION : {{ $act->dependencia->direccion}} </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="10" style="text-align: left;"><strong>INTERVIENEN EN LA ENTREGA: </strong> </td>
         </tr>
         <tr>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">ENTIDAD/EMPRESA</td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">NOMBRE </td>
          <td class="coluyy2 fondofila" colspan="3" style="text-align: center">CARGO</td>
          <td class="coluyy2 fondofila" colspan="3" style="text-align: center" >EN CALIDAD DE</td>
         </tr>
         <tr>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->contrato->proveedor->nombre}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->nombre_entrega}}</td>
          <td class="coluyy2 " colspan="3" style="text-align: left">{{$act->cargo_entrega}}</td>
          <td class="coluyy2 " colspan="3" style="text-align: left">{{$act->en_calidad_de}}</td>
         </tr>
         <tr>
          <td class="coluyy2 " colspan="2" style="text-align: left">AGENCIA LOGISTICA DE LAS FUERZAS MILITARES REGIONAL NORTE</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->user->name}}</td>
          <td class="coluyy2 " colspan="3" style="text-align: left">{{ Str::startsWith(strtolower($act->dependencia->name), 'comedor') ? 'Administrador '.$act->dependencia->name : $act->dependencia->name }}</td>
          <td class="coluyy2 " colspan="3" style="text-align: left">REPRESENTANTE DE LA ALFM REG NORTE</td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="10" style="text-align: left;"><strong>EN CUMPLIMIENTO DE CONTRATO INTERADMINISTRATIVO No.: </strong> {{ $act->contrato->contrainter->detalle}} </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="10" style="text-align: left;"><strong>ENTIDAD CONTRATANTE/CLIENTE</strong> MINISTERIO DE DEFENZA NACIONAL (EJERCITO)</td>
         </tr>
          <tr >
           <td class="colacta" colspan="10" style="text-align: center;">DATOS DE LOS BIENES Y/O SERVICIOS A ENTREGAR Y/O RECIBIR </td>
         </tr>
         <tr class="">
          <td class="coluyy2" colspan="10" style="text-align: left;"><strong>SE HACE ENTREGA DE  LOS BIENES Y /O SERVICIOS DISCRIMINADOS A CONTINUACION: </strong> </td>
         </tr>
         <tr>
          <td class="coluyy2 fondofila" colspan="1"  >ITEM</td>
          <td class="coluyy2 fondofila" colspan="3" >DESCRIPCION</td>
          <td class="coluyy2 fondofila" colspan="1" >CANTIDAD</td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: right" >V/UNITARIO</td>
          <td class="coluyy2 fondofila" colspan="3" style="text-align: right">VTOTAL</td>
        </tr>
         @php $c=0     @endphp
        @foreach ($act->factura->lineas as $linea)
            @php $c += 1     @endphp
          <tr>
            <td class="coluyy2 " colspan="1"  >{{ $c}}</td>
            <td class="coluyy2 " colspan="3" >{{$linea->itemcontrato->producto->name ?? '—' }}</td>
            <td class="coluyy2 " colspan="1" >{{$linea->cantidad}}</td>
            <td class="coluyy2 " colspan="2" style="text-align: right" >{{number_format($linea->valor_base, 0, ',', '.')}}</td>
            <td class="coluyy2 " colspan="3" style="text-align: right">{{number_format($linea->valor_base * $linea->cantidad, 0, ',', '.')}}</td>
          </tr>
        @endforeach
         <tr >
            <td class="colacta" colspan="10" style="text-align: center;">VALOR PARCIAL 2 </td>
         </tr>
         <tr>
          <td class="coluyy2 " colspan="4" >Administracion</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
        </tr>
         <tr>
          <td class="coluyy2 " colspan="4" >Imprevistos</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
        </tr>
         <tr>
          <td class="coluyy2 " colspan="4" >Utilidades</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
          <td class="coluyy2 " colspan="3" >{{""}}</td>
        </tr>
        <tr>
          <td class="coluyy2 fondofila" colspan="7" style="text-align: right">VALOR  SIN  I.V.A</td>
          <td class="coluyy2 fondofila" colspan="3" style="text-align: right">{{number_format($act->factura->subtotal, 0, ',', '.')}}</td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="7" style="text-align: right">VALOR  I.V.A  </td>
          <td class="coluyy2 " colspan="3" style="text-align: right">{{number_format($act->factura->total_iva, 0, ',', '.')}}</td>
        </tr>
        <tr>
          <td class="coluyy2 fondofila" colspan="7" style="text-align: right">VALOR  TOTAL</td>
          <td class="coluyy2 " colspan="3" style="text-align: right">{{number_format($act->factura->subtotal + $act->factura->total_iva, 0, ',', '.')}}</td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;"></td>
         </tr>
         <tr >
          <td class="coluy" colspan="10" style="text-align: left;">CUMPLIENDO LOS REQUISITOS ESTABLECIDOS EN: MARQUE CON UNA  X LOS QUE APLIQUEN</td>
         </tr>
         <tr >
          <td class="coluy" colspan="2"  style="text-align: left;">OBJETO DEL CONTRATO:</td>
          <td class="coluyy2"  style="text-align: center;">X</td>
          <td class="coluy" colspan="1" style="text-align: center;">ANEXO DEL CONTRATO </td>
          <td class="coluyy2"  style="text-align: center;">X</td>
          <td class="coluy"  colspan="1" style="text-align: center;">FICHA TECNICA :</td>
          <td class="coluyy2"  style="text-align: center;">X</td>
          <td class="coluy"  colspan="2" style="text-align: center;">ESPECIFICACIONES TECNICAS:</td>
          <td class="coluyy2"  style="text-align: center;">X</td>
         </tr>
         <tr >
          <td class="coluy" colspan="2"  style="text-align: left;">NORMA TECNICA:</td>
          <td class="coluy"  style="text-align: center;"></td>
          <td class="coluy" colspan="7" style="text-align: left;">CUAL?:</td>
         </tr>
         <tr>
           <td class="coluyy2 " colspan="10" style="text-align: left">EVIDENCIADO DE ACUERDO CON LA APLICACIÓN DE :MARQUE CON UNA  X A LOS QUE APLIQUEN</td>
         </tr>
         <tr>
          <td class="coluyy2 " colspan="3" style="text-align: left">ACTIVIDAD</td>
          <td class="coluyy2 " colspan="1" style="text-align: left">APLICA</td>
          <td class="coluyy2 " colspan="1" style="text-align: left">NO APLICA</td>
          <td class="coluyy2 " colspan="5" style="text-align: left">OBSERVACIONES</td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="3" style="text-align: left">INSPECCION VISUAL REALIZADA</td>
          <td class="coluyy2 " colspan="1" style="text-align: center">X</td>
          <td class="coluyy2 " colspan="1" style="text-align: left"></td>
          <td class="coluyy2 " colspan="5" style="text-align: left"></td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="3" style="text-align: left">INFORMES DE LABORATORIO  REALIZADOS</td>
          <td class="coluyy2 " colspan="1" style="text-align: center">X</td>
          <td class="coluyy2 " colspan="1" style="text-align: left"></td>
          <td class="coluyy2 " colspan="5" style="text-align: left"></td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="3" style="text-align: left">CERTIFICACION EXPEDIDA </td>
          <td class="coluyy2 " colspan="1" style="text-align: center">X</td>
          <td class="coluyy2 " colspan="1" style="text-align: left"></td>
          <td class="coluyy2 " colspan="5" style="text-align: left"></td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="3" style="text-align: left">OTROS</td>
          <td class="coluyy2 " colspan="1" style="text-align: left"></td>
          <td class="coluyy2 " colspan="1" style="text-align: left"></td>
          <td class="coluyy2 " colspan="5" style="text-align: left"></td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;"></td>
        </tr>
        <tr >
          <td class="colacta" colspan="10" style="text-align: center;">DATOS DEL RECIBO PARCIAL O TOTAL DE LOS BIENES O SERVICIOS</td>
        </tr>
        <tr >
          <td class="coluyy2" colspan="10" style="text-align: center;">TRATA DEL RECIBO A SATISFACCION QUE HACE LA ENTIDAD CONTRATANTE /CLIENTE</td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;"></td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;">UNA VEZ REVISADOS LOS BIENES OBJETO DEL CONTRATO, POR PARTE DE: MARCAR CON UNA  X A LOS QUE APLIQUEN</td>
        </tr>
        <tr>
          <td class="coluy" colspan="2"  style="text-align: left;">COMITE TECNICO:</td>
          <td class="coluyy2"  style="text-align: center;"></td>
          <td class="coluy" colspan="1" style="text-align: center;">SUPERVISOR </td>
          <td class="coluyy2"  style="text-align: center;">X </td>
          <td class=" " colspan="5" style="text-align: left"></td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;">OBSERVACIONES GENERALES : FACTURA ELECTRONICA DE VENTA No. {{$act->numfactura}}</td>
        </tr>
        <tr >
          <td class="coluy" colspan="10" style="text-align: left;"></td>
        </tr>
        <tr >
          <td class="colacta" colspan="10" style="text-align: center;">PARA CONSTANCIA DE LO ANTERIOR, INTERVIENEN :</td>
        </tr>
        <tr>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">ENTIDAD/EMPRESA</td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">NOMBRE </td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">CARGO</td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center" >EN CALIDAD DE</td>
          <td class="coluyy2 fondofila" colspan="2" style="text-align: center">FIRMA</td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->contrato->proveedor->nombre}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->nombre_entrega}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->cargo_entrega}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->en_calidad_de}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left"></td>
        </tr>
        <tr>
          <td class="coluyy2 " colspan="2" style="text-align: left">AGENCIA LOGISTICA DE LAS FUERZAS MILITARES REGIONAL NORTE</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{$act->user->name}}</td>
          <td class="coluyy2 " colspan="2" style="text-align: left">{{ Str::startsWith(strtolower($act->dependencia->name), 'comedor') ? 'Administrador '.$act->dependencia->name : $act->dependencia->name }}</td> <!--//$act->dependencia->nombredepen-->
          <td class="coluyy2 " colspan="2" style="text-align: left">REPRESENTANTE DE LA ALFM REG NORTE</td>
          <td class="coluyy2 " colspan="2" style="text-align: left"> </td>
        </tr>
  </body>
</html>
