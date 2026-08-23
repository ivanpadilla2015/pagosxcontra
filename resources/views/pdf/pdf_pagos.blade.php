<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="{{ public_path('css/bootstrap.min.css')}}" >
    <link rel="stylesheet" href="{{ public_path('css/stylos.css')}}">
    <title>Reporte Pago Financiero </title>
   </head>
  <body>
    <div>
        <img src="{{ public_path('img/GF-FO-35_2023.JPG')}}" class="img-fluid w-100" alt="Responsive image ">
    </div>
    <table >
        <tbody>
           <tr class="colu">
                <td class="coluyy1" colspan="9" style="text-align: center; background-color: #F5F5F5; "><strong>DATOS DEL CONTRATO</strong></td>
           </tr>
           <tr>
            <td class="coluyy" colspan="1" style="background-color: #F5F5F5;"><strong>CONTRATO No.</strong></td>
            <td class="coluyy2" colspan="2" >{{ $data->contrato->numcontrato }}</td>
            <td class="coluyy" colspan="3" style="text-align: left; background-color: #F5F5F5;"><strong>No. Registro Presupuestal/Fecha </strong> </td>
            <td class="coluyy" colspan="3" style="text-align: center;" >{{ $regpres }}</td>
          </tr>
          <tr>
            <td class="coluyy"  style="background-color: #F5F5F5;"><strong>Objeto del contrato</strong></td>
            <td class="colul" colspan="2">{{ $data->contrato->objetocontrato }}</td>
            <td class="coluyy" style="background-color: #F5F5F5;" >RP ACTUAL No</td>
            <td class="colul" >
              @foreach ($datoreg as $item)
                 @if ($item->total_rubro > 0)
                     {{$item->numero_reg.'-'}}
                  @else 
                    {{ $numreg }}
                 @endif  
              @endforeach 
          </td>
            <td class="colul" style="background-color: #F5F5F5;"  >VALOR</td>
            <td class="colul" style="text-align: center;" >
                  @foreach ($datoreg as $item)
                      @if ($item->total_rubro > 0)
                          {{number_format($item->valor_reg, 2, ',', '.').'-'}}
                         
                      @endif  
                  @endforeach
              
            </td>
            <td class="colul" style="background-color: #F5F5F5;" >FECHA</td>
            <td class="colul" style="text-align: center;">
                      @foreach ($datoreg as $item)
                        @if ($item->total_rubro > 0)
                            {{$item->fecha_reg.','}}
                            @else 
                             {{ $ferg }}
                        @endif  
                      @endforeach 
            </td>
           </tr>
           <tr>
            <td class="coluyy"  style="background-color: #F5F5F5;"><strong>Nombre Contratista: </strong></td>
            <td class="coluyy" colspan="2">{{ $data->contrato->proveedor->nombre }}</td>
            <td class="coluyy" style="background-color: #F5F5F5;">RP RESERVA No</td>
            <td class="coluyy" ></td>
            <td class="coluyy" style="background-color: #F5F5F5;"  >VALOR</td>
            <td class="coluyy" ></td>
            <td class="coluyy" style="background-color: #F5F5F5;" >FECHA</td>
            <td class="coluyy" ></td>
           </tr>
           <tr>
            <td class="coluyy" colspan="2" style="text-align: left; background-color: #F5F5F5; "><b>Nombre de quien entrega el bien o servicio:</b></td>
            <td class="coluyy" colspan="2" ></td>
            <td class="coluyy" colspan="2" style="text-align: left; background-color: #F5F5F5; ">No. de la  negociación de BMC</td>
            <td class="coluyy" colspan="3" ></td>
          </tr>
          <tr>
            <td class="colul" colspan="3" style="text-align: left; background-color: #F5F5F5; ">Presupuesto  con cargo  a ALFM:</td>
            <td class="colul"  style="text-align: center; background-color: #F5F5F5; " >SI</td>
            <td class="colu"></td>
            <td class="colul"  style="text-align: center; background-color: #F5F5F5; " >NO</td>
            <td class="colu"></td>
            <td class="colu" colspan="2"></td>
        </tr>  
        <tr>
            <td class="colul" colspan="3" style="text-align: left; background-color: #F5F5F5; ">Con cargo al (los) contrato(s) interadministrativo(s)  No. /Convenio u otras</td>
            <td class="colul" colspan="3"  >{{ $data->contrato->contrainter->detalle}}</td>
            <td class="colul"  style="text-align: center; background-color: #F5F5F5; ">Plazo Ejecucion</td>
            <td class="colul" colspan="2">{{ $data->contrato->contrainter->plazoejecucion ?? ($ultreg->newplazoejecucion ? Carbon\Carbon::parse($ultreg->newplazoejecucion)->format('d/m/Y') : '-') }}</td>
        </tr>
        <tr>
            <td class="colul" colspan="2"  style="text-align: left; background-color: #F5F5F5; ">Celebrado(s) con:</td>
            <td class="colul"  colspan="7">{{ $data->contrato->contrainter->concargo_a}}</td>
        </tr>
        <tr>
            <td class="colu" colspan="9" style="text-align: center; "></td>>
        </tr>
        <tr>
            <td class="coluyy2" colspan="9" style="text-align: center; background-color: #F5F5F5; "><b>EJECUCION ANTICIPO</b></td>>
        </tr>
        <tr>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Valor Anticipo Pactado</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Valor amortizar</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Total amortizado</td>
            <td class="colu" colspan="3" style="text-align: center; background-color: #F5F5F5; ">Saldo pendiente por amortizar</td>
          </tr>
          <tr>
            <td class="colu" colspan="2" style="text-align: center;">0,00</td>
            <td class="colu" colspan="2" style="text-align: center;">0,00</td>
            <td class="colu" colspan="2" style="text-align: center;">0,00</td>
            <td class="colu" colspan="3" style="text-align: center;">0,00</td>
          </tr>
          <tr>
            <td class="colu" colspan="9" style="text-align: center; "></td>>
          </tr>
          <tr>
            <td class="coluyy2" colspan="6" style="text-align: center; background-color: #F5F5F5; "><b>CONTROL CONTRATO</b></td>>
            <td class="colul" colspan="1" style="text-align: center; background-color: #F5F5F5; ">VALOR</td>
            <td class="colul" colspan="2" style="text-align: center; background-color: #F5F5F5; ">{{number_format($tcontrato, 2, ',', '.')}}</td>
          </tr>
          <tr>
            <td class="colu" colspan="1" style="text-align: center; background-color: #F5F5F5; ">Numero de RP</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Rubro presupuestal</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Descripción del rubro</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Dependencia de afectación</td>
            <td class="colu" colspan="2" style="text-align: center; background-color: #F5F5F5; ">Valor por rubro</td>
          </tr>
          @foreach ($data->contrato->movirubros as $item)
          <tr>
            <td class="colu" colspan="1" style="text-align: center;">{{$item->registro->numero_reg}}</td>
            <td class="colul" colspan="2" style="text-align: center;">{{$item->rubro->codigo_rubro}}</td>
            <td class="colul" colspan="2" style="text-align: center;">{{$item->rubro->nombre_rubro}}</td>
            <td class="colu" colspan="2" style="text-align: center;">@if ($item->dependencia_afectacion){{$item->dependencia_afectacion;}}
              @else {{$item->registro->dependencia_afectacion;}} @endif</td>
            <td class="colu" colspan="2" style="text-align: center;">{{number_format($item->valor_rubro, 2, ',', '.')}}</td>
          </tr> 
          @endforeach
          
          <tr>
            <td class="coluy" colspan="5" style="text-align: center;"></td>
            <td class="colu" colspan="2" style="text-align: center;">Valor total del contrato</td>
            <td class="colu" colspan="2" style="text-align: center;">{{number_format($tcontrato, 2, ',', '.')}}</td>
          </tr>
          <tr >
            <td class="coluy" colspan="5" style="text-align: center;"></td>
            <td class="colu" colspan="2" style="text-align: center;">Valor ejecutado presupuestal </td>
            <td class="colu" colspan="2" style="text-align: center;">{{number_format($ejecutado, 2, ',', '.')}}</td>
          </tr>
          <tr>
            <td class="coluy" colspan="5" style="text-align: center;"></td>
            <td class="colu" colspan="2" style="text-align: center;">Saldo por ejecutar</td>
            <td class="colu" colspan="2" style="text-align: center;">{{number_format($tsaldocontra, 2, ',', '.')}}</td>
          </tr>
          <tr>
            <td class="coluy" colspan="9" style="text-align: center;"></td>
          </tr>
          <tr>
            <td class="coluyy2" colspan="9" style="text-align: center; background-color: #F5F5F5;">NÚMERO DE TRÁMITE DE PAGO #{{substr($data->numero,0,3)}}</td>
          </tr>
           <tr>
            <td class="colu">No. de documento</td>
            <td class="colu">Fecha del documento</td>
            <td class="colu">Rubro presupuestal</td>
            <td class="colu">Código del uso presupuestal</td>
            <td class="colu">Descripción del  uso</th>
            <td class="colu">Dependencia de afectación</td>
            <td class="colu">Valor factura</td>
            <td class="colu">Valor a pagar</td>
            <td class="colu">Valor ejecutado</td>
          </tr>
          @php $s=0;    @endphp
          @foreach ($data->rubrosSnapshot as $value)
               @php 
                  $t1 = $data->detalles()->where('movirubro_id', $value->movirubro_id)->sum('valor_pagado'); 
                  $porrubro = $data->detalles()->where('movirubro_id', $value->movirubro_id)->get();
                  $sald1 = ($value->valor_rubro - $value->saldo_rubro) - $t1;                  
               @endphp 
              @foreach ($porrubro as $item)
              <tr>
                <td class="colul">{{explode('-', $item->factura->numero)[1] ?? $item->factura->numero}} </td>
                <td class="colul">{{ \Carbon\Carbon::parse($item->factura->fecha)->format('d/m/Y')}}</td>
                <td class="colul">{{$item->movirubro->rubro->codigo_rubro}}</td>
                <td class="colul">{{$item->uso->codigo_uso}}</td>
                <td class="coluyy">{{$item->uso->nombre_uso}}</td>
                <td class="colul">@if ($item->movirubro->dependencia_afectacion){{$item->movirubro->dependencia_afectacion;}}
              @else {{$item->movirubro->registro->dependencia_afectacion;}} @endif</td>
                <td class="colul" style="text-align: center;">{{number_format($item->valor_pagado, 2, ',', '.')}}</td>
                <td class="colul" style="text-align: center;">{{number_format($item->valor_pagado, 2, ',', '.')}}</td>
                <td class="colul" style="text-align: center;">{{ number_format($sald1 += $item->valor_pagado, 2, ',', '.') }}</td>
              </tr>
            
                @php $s += $item->valor_pagado @endphp
              @endforeach
          @endforeach
          <tr>
          
            <td class="colul" colspan="3">TOTAL TRAMITE</th>
            <td class="colul" colspan="6" style="text-align: center;" >{{number_format($s, 2, ',', '.')}}</td>
          </tr>
          @foreach ($datoUso as $value)
            
              <tr>
                <td class="colul" colspan="2">Saldo por uso</th>
                <td class="colul">{{$value->codigo_uso}} </th>
                <td class="colul" colspan="6" style="text-align: center;" >{{ number_format($value->saldo_rubro, 2, ',', '.')}}</td>
              </tr>
            
          @endforeach
           <tr>
                <td class="colul" colspan="2">Saldo </th>
                <td class="colul"> </th>
                <td class="colul" colspan="6" style="text-align: center;" >{{number_format($tsaldocontra, 2, ',', '.')}}</td>
           </tr>
          
          <tr>
            <td class="coluyy2" colspan="9" style="text-align: center; background-color: #F5F5F5;">ADICIÓN / REDUCCIÓN/TRASLADOS  DEL RP No. DE FECHA _________  Y FECHA DEL MOVIMIENTO (SI APLICA) _____________</td>
          </tr>
          <tr>
            <td class="colu" colspan="2" style="background-color: #F5F5F5;">Movimiento</td>
            <td class="colu" colspan="2" style="background-color: #F5F5F5;">Fecha</td>
            <td class="colu" colspan="3" style="background-color: #F5F5F5;"></td>
            <td class="colu" colspan="2" style="background-color: #F5F5F5;">Valor</td>            
          </tr>
          @foreach ($data->registrosSnapshot as $item)
            @if ($item->tiporegistro_id > 1)
              <tr>
                <td class="colul" colspan="2" style="background-color: #F5F5F5;">{{$item->tiporegistro->nombre_tipo_reg}}</td>
                <td class="colul" colspan="2" style="background-color: #F5F5F5;">{{$item->fecha_reg}}</td>
                <td class="colul" colspan="3" style="background-color: #F5F5F5;"></td>
                <td class="colul" colspan="2" style="background-color: #F5F5F5;">{{number_format($item->valor_reg, 2, ',', '.')}}</td>            
              </tr>
            @endif
          @endforeach
     
        </tbody>
      </table>

      <table>
        
          <tr >
            <td class="coluy" ></td>
            <td class="coluy" ></td>
            <td class="coluy" ></td>
            <td class="coluy" ></td>
            <td class="coluy" ></td>
            <td class="coluy" ></td>
           
          </tr>
          <tr >
            <td class="coluy" >Firma Supervisor:</td>
            <td class="coluy" >_________________________</td>
            <td class="coluy" style="text-decoration: underline;" >Nombre de quien Revisa en Presupuesto:</td>
            <td class="coluy" > {{$data->contrato->user->regional->presupuesto->jefe_presupueto}}</td>
            <td class="coluy">Nombre de quien Revisa en Cuentas por pagar y/o Contabilidad:</td>
            <td class="coluy" >_______________________ </td>
          </tr>
          <tr >
            <td class="coluy">Nombre del Supervisor:</td>
            <td class="coluy" style="text-decoration: underline;" >{{$data->contrato->user->name}}</td>
            <td class="coluy">Fecha en que validan los Saldos "RP": </td>
            <td class="coluy" >{{"______________________"}}</td>
            <td class="coluy">Fecha:</td>
            <td class="coluy" >_________________________</td>
          </tr>
          <tr >
            <td class="coluy" >Supervisor de Cto No:</td>
            <td class="coluy" style="text-decoration: underline;">{{'____'.$data->contrato->numcontrato.'____'}}</td>
            <td class="coluy">Firma de Quien Revisa:</td>
            <td class="coluy" >{{"__________________________"}}</td>
            <td class="coluy">Firma de quien Revisa:</td>
            <td class="coluy" >____________________________</td>
          </tr>
      </table>
     
      
  </body>

</html>