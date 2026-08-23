<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="{{ public_path('css/stylos_info.css')}}">
    <title>Informe Supervisor</title>
    <style>
      body{
      }
    </style>
   </head>
  <body>
 		  <div>
  			<img src="{{ public_path('img/CT-FO-11.JPG')}}" width="100%"  alt="Responsive image ">
      </div>
      @php  $fein = $data->fechainfo;
            $fechin = new DateTime($fein);
            $fecha_d_m_y = $fechin->format('d-m-Y'); @endphp 
      <table>
        <tr>
        <td colspan="4" style="text-align: center" ><strong>INFORME DE SUPERVISIÓN No. {{ $data->cansecu_infor}}</strong></td>
        </tr>
        <tr>
          <td colspan="4" style="text-align: right" > <small>{{  'Malambo'.", ". $fecha_d_m_y }}</small> </td>
          </tr>
      </table>
      <strong>Al</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{":".strtoupper($data->user->regional->director->name)}}<br/>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$data->user->regional->director->cargo}}
      <br> <br>
      <div align="justify" >{{"Con toda atención me permito enviar al señor ". $data->user->regional->director->nombre_director.", el informe de supervisión No.".$data->cansecu_infor."  Del contrato No."}} {{$data->contrato->numcontrato}}  Correspondiente {{$data->corresponde_periodo}}, de acuerdo con la siguiente información:</div>
      <div align="justify" >{{"Tener en cuenta para el diligenciamiento del informe las instrucciones impartidas en el Manual de Contratación."}}</div>
      <table>
        <tr>
          <td colspan="2"><strong>1.	DATOS GENERALES DEL CONTRATO:</strong>  (De acuerdo con la notificación enviada por la Agencia Logística, se deberán establecer los datos)</td>
          
        </tr>
        <tr>
            <td colspan="2" class="colu"><strong>CONTRATO No.</strong> {{$data->contrato->numcontrato}} </td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
            <td colspan="2" class="colu"><strong>CONTRATISTA:</strong> {{$data->contrato->proveedor->nombre}}</td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
            <td colspan="2" class="colu" align="justify" ><strong>OBJETO:</strong> {{$data->contrato->objetocontrato}}</td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
       
        <tr>
      
            <td colspan="2" class="colu" align="justify" ><strong>VALOR DEL CONTRATO:</strong>  {{ number_format($primer->valor_reg, 0, ',', '.') }}</td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
            @php
                $fe = new DateTime($ult->newplazoejecucion);  $fepla = $fe->format('d/m/Y'); 
            @endphp 
           <td colspan="2" class="colu" align="justify" ><strong>PLAZO DE EJECUCION DEL CONTRATO:</strong>{{$fepla}}</td>
             
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
          @php
            $canti =  $data->informeregistros->count();
          @endphp
            <td colspan="2" class="colu" align="justify" ><strong>MODIFICACIONES : </strong> SI_{{ $res = ($canti > 1 ? 'x':'_') }}__ No_{{ $res = ($canti = 1 ? 'x':'_') }}_</td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
          <td class="colu" align="center">TIPO MODIFICACION</td>
          <td class="colu"></td>
      </tr>
      @php $ca = 0; $cr = 0;  @endphp
      @foreach ($data->informeregistros as $itemadi)
        
        
        @if ($itemadi->tiporegistro_id == 2)
          @php $ca += 1; @endphp
          <tr>
            <td class="colu" align="justify">{{'Adicion N° '.$ca}}</td>
            <td class="colu" align="justify">{{ number_format($itemadi->valor_reg) }}</td>
          </tr>
        @else
            @if ($itemadi->tiporegistro_id == 3)
                @php $cr += 1; @endphp
                <tr>
                  <td class="colu" align="justify">{{'Reducción N° '.$cr}}</td>
                  <td class="colu" align="justify">{{ number_format($itemadi->valor_reg) }}</td>
                </tr>
            @endif
        @endif
        
      @endforeach
        <tr>
          <td class="colu" align="justify"></td>
          <td class="colu" align="justify"></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        
        <tr>
            <td colspan="2" align="justify" ><strong>CONTROL OBLIGACIONES Y ENTREGABLES CONTRACTUALES: </strong> </td>
            
        </tr>
        <tr>
            <td colspan="2" align="justify" >Se recibio lo acordado en el contrato sin ningún inconveniente </td>
            
        </tr>
        <tr>
            <td colspan="2"></td>
            
        </tr>
        <tr>
            <td colspan="2" align="justify" >Una vez verifique el pliego de condiciones, oferta y contrato (pagina web, SECOP), proceda a diligenciar el presente cuadro de control de cumplimiento de obligaciones. </td>
            
        </tr>
       
      </table>
      <table>
        <tr>
            <td ></td>
            <td ></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="t1" align="center" style="background-color:  #dedbda ">DESCRIPCION</td>
            <td colspan="12" class="t1" align="center" style="background-color:  #dedbda ">PLAZO EJECUCION</td>
            
         </tr>
        <tr>
            <td class="t2" style="background-color:  #dedbda ">NUMERAL</td>
            <td class="tn" style="background-color:  #dedbda ">OBLIGACION</td>
            <td class="t1" style="background-color:  #dedbda ">ENTREGABLE</td>
            <td class="t2" style="background-color:  #dedbda ">ENE</td>
            <td class="t2" style="background-color:  #dedbda ">FEB</td>
            <td class="t2" style="background-color:  #dedbda ">MAR</td>
            <td class="t2" style="background-color:  #dedbda ">ABR</td>
            <td class="t2" style="background-color:  #dedbda ">MAY</td>
            <td class="t2" style="background-color:  #dedbda ">JUN</td>
            <td class="t2" style="background-color:  #dedbda ">JUL</td>
            <td class="t2" style="background-color:  #dedbda ">AGO</td>
            <td class="t2" style="background-color:  #dedbda ">SEP</td>
            <td class="t2" style="background-color:  #dedbda ">OCT</td>
            <td class="t2" style="background-color:  #dedbda ">NOV</td>
            <td class="t2" style="background-color:  #dedbda ">DIC</td>
        </tr>
        @foreach ($data->informeobligaciones as $item)
            <tr>
                <td class="t2">{{ $item->numeral }}</td>
                <td class="tn">{{ $item->obligacion_deta }}</td>
                <td class="t1">{{ $item->entregable }}</td><!-- $action = (4 >= $data->contrato->num_mes  && 4 <= $data->mes_ejecucion) ? 'x' : '' -->
                <td class="t2" align="center">{{ $action = (1 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (2 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (3 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (4 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (5 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (6 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (7 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (8 == $data->mes_ejecucion) ? $item->confirmar : '' }} </td>
                <td class="t2" align="center">{{ $action = (9 == $data->mes_ejecucion) ? $item->confirmar : '' }} </td>
                <td class="t2" align="center">{{ $action = (10 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (11 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
                <td class="t2" align="center">{{ $action = (12 == $data->mes_ejecucion) ? $item->confirmar : '' }}</td>
            </tr>
          @endforeach
       
      </table>
                       
      <br><br>
      <STRong>2. CUMPLIMIENTO DE LOS RIESGOS PREVISIBLES DEL CONTRATO.</STRong><br>
       <span align="justify" >Seguimiento y monitoreo delos riesgos establecidos para la etapa de ejecución del contrato:</span> 
      <table>
        <tr>
         <td class="t2" >No</td>
         <td class="t1" >TIPO</td>
         <td class="t1" >DESCRIPCION</td>
         <td class="t1" >TRATAMIENTO</td>
         <td class="t1" >RESPONSABLE</td>
         <td class="t1" >PERIODICIDAD</td>
      </tr>
      @php $c= 0; @endphp
      @foreach ($data->informeriesgos as $item)
          @php $c += 1; @endphp
          <tr>
            <td class="t2" >{{ $c }}</td>
            <td class="t1" >{{$item->tipo}}</td>
            <td class="t1" >{{$item->descripcion}}</td>
            <td class="t1" >{{$item->tratamiento}}</td>
            <td class="t1" >{{$item->responsable}}</td>
            <td class="t1" >{{$item->periodicidad}}</td>
          </tr>
      @endforeach
      </table>
      <strong>3.	NOVEDADES DE INCUMPLIMIENTO EN LA EJECUCION</strong> <br>
       {{$data->novedad}}
      <br><br>
      <strong>4.	CONTROL DE PAGOS</strong> 
      <br><br>
      <table>
          <tr>
              <td colspan="2" class="colu">Valor Total del contrato</td>
              <td colspan="4" class="colu">{{ number_format($tcontrato, 0, ',', '.')}}</td>
          </tr>
          @php $ca = 0; $cr = 0; $rega = ''; $vc = $tcontrato @endphp
          @foreach ($data->informeregistros as $itemadi)
                     
            @if ($itemadi->tiporegistroid == 2)
                @php $vc += $itemadi->valor_reg; $ca += 1; @endphp
              <tr>
                <td colspan="2" class="colu" >{{'Adicion N° '.$ca}}</td>
                <td colspan="4" class="colu">{{ number_format($itemadi->valor_reg) }}</td>
                
              </tr>
            @else
              @if ($itemadi->tiporegistroid == 3)
                @php $vc -= $itemadi->valor_reg; $cr += 1; @endphp
                <tr>
                  <td colspan="2" class="colu" >{{'Reducción N° '.$cr}}</td>
                  <td colspan="3" class="colu">{{ number_format($itemadi->valor_reg) }}</td>
                  
                </tr>
              @endif
            @endif
            
        @endforeach
         
          <tr>
            <td colspan="2" class="colu" style="background-color:  #dedbda "><strong>No. Factura y/o Cuenta de cobro</strong> </td>
            <td  class="colu" style="background-color:  #dedbda " ><strong>Fecha</strong> </td>
            <td  class="colu" style="background-color:  #dedbda "><strong>Valor Facturado</strong> </td>
            <td  class="colu" style="background-color:  #dedbda "><strong>Saldo Por Ejecutar</strong></td>
            <td  class="t3" style="background-color:  #dedbda " align="justify"><strong>Con Cargo al contrato Interadministrativo No. {{$data->contrato->contrainter->detalle}} </strong></td>
          </tr>
          @php $s = 0; $sdo = $data->saldo_viene+ $data->total_info; @endphp
          @foreach ($datofac as $fac)
             @php $s += $fac->valorfac; $sdo -= $fac->valorfac;
                $fe = new DateTime($fac->fechafac);  $fefa = $fe->format('d/m/Y');
              @endphp
            <tr>
              <td colspan="2" class="colu" >{{'FACTURA: '.$fac->numfac}}</td>
              <td  class="colu" >{{ $fefa }}</td>
              <td  class="colu" >{{ number_format($fac->valorfac, 2, ',', '.') }}</td>
              <td  class="colu" >{{ number_format($sdo, 2, ',', '.')}}</td>
              <td  class="colu" ></td>
            </tr>
          @endforeach
          <tr>
            <td colspan="2" class="colu" style="background-color:  #dedbda " >Subtotal (por Usos) </td>
            <td  class="colu" style="background-color:  #dedbda " ></td>
            <td  class="colu" style="background-color:  #dedbda " ></td>
            <td  class="colu" style="background-color:  #dedbda "></td>
            <td  class="colu" style="background-color:  #dedbda "></td>
          </tr>
          @foreach ($datoUso as $conuso)
            <tr>
              <td colspan="2" class="colu" >{{$conuso->nombre_uso}} </td>
              <td  class="colu" ></td>
              <td  class="colu" >{{ number_format($conuso->total_fac, 2, ',', '.')}}</td>
              <td  class="colu" ></td>
              <td  class="colu" ></td>
            </tr>
          @endforeach
      </table>
      
      Nota: El supervisor verificará que el contratista realice el cargue de las facturas en el SECOP II. 
      <br>
       &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>Porcentaje cumplimiento Avance %</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;  {{ $action = ($data->porcentaje_cumplimiento < 0) ? "0" : number_format($data->porcentaje_cumplimiento, 2, ',', '.') }}% &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Demora _____________% <br>
      <br>
       <strong>5.	INFORMACIÓN FIDUCIAS ( Cuando aplique)</strong> <br>
       {{$data->fiducia}}
      <br><br>
      <strong>6.	INFORMACIÓN PERSONAL CONTRATADO (Cuando aplique)</strong> <br>     
      {{$data->infopersonal}} 
      <br><br>
      <strong>7. INFORMACION AIU </strong> <br>
      {{$data->infoaiu}} 
      <br><br>
     <strong>8. DOCUMENTOS ANEXOS AL INFORME </strong> <br>
      {{$data->anexos}} 
      <br><br>
      <strong>9. REEVALUACION PROVEEDORES </strong> <br>
       N/A
       <br><br>
       <table>
          <tr>
            <td  rowspan="2" colspan="2" class="colu" style="background-color:  #dedbda "><strong>FACTORES A EVALUAR</strong> </td>
            <td  colspan="2" class="colu" style="background-color:  #dedbda " align="center"><strong>CUMPLE</strong> </td>
            <td  rowspan="2" colspan="2" class="colu" style="background-color:  #dedbda "><strong>OBSERVACIONES</strong></td>
          </tr>
          <tr>
            
            <td  class="colu" style="background-color:  #dedbda " align="center" ><strong>SI</strong> </td>
            <td  class="colu" style="background-color:  #dedbda " align="center"><strong>NO</strong> </td>

          </tr>
          <tr>
            <td  colspan="2" class="colu" align="justify" >Nivel de Cumplimiento con el objeto del contrato y
especificaciones técnicas del mismo.</td>
            <td  class="colu"  ></td>
            <td  class="colu" > </td>
            <td  colspan="2" class="colu" align="justify" ></td>
          </tr>
          <tr>
            <td  colspan="2" class="colu" align="justify" >Nivel de Cumplimiento con el tiempo de entrega.</td>
            <td  class="colu"  ></td>
            <td  class="colu" > </td>
            <td  colspan="2" class="colu" align="justify" ></td>
          </tr>
          <tr>
            <td  colspan="2" class="colu" align="justify" >Nivel de Cumplimiento con las obligaciones
adicionales estipuladas en el contrato.</td>
            <td  class="colu"  ></td>
            <td  class="colu" > </td>
            <td  colspan="2" class="colu" align="justify" ></td>
          </tr>
          <tr>
            <td  colspan="2" class="colu" align="justify" >Nivel de Cumplimiento con el precio ofertado.</td>
            <td  class="colu"  ></td>
            <td  class="colu" > </td>
            <td  colspan="2" class="colu" align="justify" ></td>
          </tr>
       </table>
      <br><br>
      <strong>10. RECOMENDACIONES </strong> <br>
      {{$data->recomendacion}}
      <br><br>
      
      <div class="centra_div">
        
          <div  >  
             ___________________________________ 
            <div>{{ $data->contrato->user->name }}</div>
            <div>Supervisor del Contrato No {{$data->contrato->numcontrato}} </div>
          </div>
       </div>
        
  		
  	
   
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    
  </body>

</html>