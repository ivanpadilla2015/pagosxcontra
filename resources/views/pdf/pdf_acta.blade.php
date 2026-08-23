<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="{{ public_path('css/bootstrap.min.css') }}">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header-img { width: 100%; margin-bottom: 10px; }
        .title-section { background-color: #f0f0f0; border: 1px solid #ccc; padding: 5px 10px; text-align: center; font-weight: bold; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td, th { border: 1px solid #ccc; padding: 4px 8px; font-size: 11px; }
        .bg-gray { background-color: #f5f5f5; }
        .bg-dark { background-color: #333; color: #fff; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .firmas td { border: none; border-bottom: 1px solid #333; height: 40px; width: 30%; }
    </style>
    <title>Acta de Recibo a Satisfacción - {{ $acta->numero }}</title>
</head>
<body>
    <div>
        <img src="{{ public_path('img/CT-FO-01_2.PNG') }}" class="header-img" alt="Encabezado">
    </div>

    {{-- Datos del contrato --}}
    <table>
        <tr>
            <td colspan="5" class="fw-bold">No. DE CONTRATO:</td>
            <td colspan="5">{{ $acta->contrato->numcontrato }}</td>
        </tr>
        <tr>
            <td colspan="5" class="fw-bold">FECHA DEL CONTRATO:</td>
            <td colspan="5">{{ $acta->contrato->fechacontrato->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">OBJETO CONTRATO:</td>
            <td colspan="7">{{ $acta->contrato->objetocontrato }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">CONTRATISTA:</td>
            <td colspan="7">{{ $acta->contrato->proveedor->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">NIT/CC/CE:</td>
            <td colspan="7">{{ $acta->contrato->proveedor->nitproveedor ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">REPRESENTANTE LEGAL:</td>
            <td colspan="7">{{ $acta->contrato->proveedor->reprelegal ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">SUPERVISOR:</td>
            <td colspan="7">{{ $acta->user->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Datos de la entrega --}}
    <div class="title-section">DATOS DE LA ENTREGA PARCIAL O TOTAL DEL BIEN O SERVICIO</div>
    <table>
        <tr>
            <td colspan="3" class="fw-bold">FECHA:</td>
            <td colspan="2">{{ $acta->fecha->format('d/m/Y') }}</td>
            <td colspan="2" class="fw-bold">HORA:</td>
            <td colspan="3">{{ $acta->hora }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">DEPENDENCIA / COMEDOR:</td>
            <td colspan="7">{{ $acta->dependencia->name ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">CIUDAD/MUNICIPIO:</td>
            <td colspan="7">{{ $acta->dependencia->municipio->nombre ?? '—' }}</td>
        </tr>
    </table>

    {{-- Intervinientes --}}
    <table>
        <tr>
            <td colspan="10" class="fw-bold">INTERVIENEN EN LA ENTREGA:</td>
        </tr>
        <tr class="bg-gray">
            <td colspan="2" class="text-center fw-bold">ENTIDAD/EMPRESA</td>
            <td colspan="2" class="text-center fw-bold">NOMBRE</td>
            <td colspan="3" class="text-center fw-bold">CARGO</td>
            <td colspan="3" class="text-center fw-bold">EN CALIDAD DE</td>
        </tr>
        <tr>
            <td colspan="2">{{ $acta->contrato->proveedor->nombre ?? '—' }}</td>
            <td colspan="2">{{ $acta->nombre_entrega }}</td>
            <td colspan="3">{{ $acta->cargo_entrega }}</td>
            <td colspan="3">{{ $acta->en_calidad_de }}</td>
        </tr>
        <tr>
            <td colspan="2">AGENCIA LOGÍSTICA DE LAS FUERZAS MILITARES</td>
            <td colspan="2">{{ $acta->user->name ?? '—' }}</td>
            <td colspan="3">Administrador {{ $acta->dependencia->name ?? '' }}</td>
            <td colspan="3">REPRESENTANTE DE LA ALFM</td>
        </tr>
    </table>

    {{-- Datos de los bienes --}}
    <div class="title-section">DATOS DE LOS BIENES Y/O SERVICIOS A ENTREGAR Y/O RECIBIR</div>
    <table>
        <tr class="bg-gray">
            <td class="text-center fw-bold">ITEM</td>
            <td colspan="3" class="fw-bold">DESCRIPCIÓN</td>
            <td class="text-center fw-bold">CANTIDAD</td>
            <td colspan="2" class="text-right fw-bold">V/UNITARIO</td>
            <td colspan="3" class="text-right fw-bold">VTOTAL</td>
        </tr>
        @php $c = 0; @endphp
        @foreach ($acta->factura->lineas as $linea)
            @php $c++; @endphp
            <tr>
                <td class="text-center">{{ $c }}</td>
                <td colspan="3">{{ $linea->itemcontrato->producto->name ?? '—' }}</td>
                <td class="text-center">{{ $linea->cantidad }}</td>
                <td colspan="2" class="text-right">${{ number_format($linea->valor_base / max(1, $linea->cantidad), 0, ',', '.') }}</td>
                <td colspan="3" class="text-right">${{ number_format($linea->valor_con_iva, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray">
            <td colspan="7" class="text-right fw-bold">VALOR S/IVA:</td>
            <td colspan="3" class="text-right fw-bold">${{ number_format($acta->factura->subtotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="7" class="text-right fw-bold">VALOR IVA:</td>
            <td colspan="3" class="text-right fw-bold">${{ number_format($acta->factura->total_iva, 0, ',', '.') }}</td>
        </tr>
        <tr class="bg-gray">
            <td colspan="7" class="text-right fw-bold">VALOR TOTAL:</td>
            <td colspan="3" class="text-right fw-bold">${{ number_format($acta->factura->subtotal + $acta->factura->total_iva, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Observaciones --}}
    <div class="title-section">OBSERVACIONES</div>
    <table>
        <tr class="bg-gray">
            <td colspan="3" class="fw-bold">ACTIVIDAD</td>
            <td class="text-center fw-bold">APLICA</td>
            <td class="text-center fw-bold">NO APLICA</td>
            <td colspan="5" class="fw-bold">OBSERVACIONES</td>
        </tr>
        <tr>
            <td colspan="3">INSPECCIÓN VISUAL REALIZADA</td>
            <td class="text-center">{{ $acta->inspeccion_visual ? 'X' : '' }}</td>
            <td class="text-center">{{ $acta->inspeccion_visual ? '' : 'X' }}</td>
            <td colspan="5">{{ $acta->inspeccion_visual }}</td>
        </tr>
        <tr>
            <td colspan="3">INFORMES DE LABORATORIO REALIZADOS</td>
            <td class="text-center">{{ $acta->informes_laboratorio ? 'X' : '' }}</td>
            <td class="text-center">{{ $acta->informes_laboratorio ? '' : 'X' }}</td>
            <td colspan="5">{{ $acta->informes_laboratorio }}</td>
        </tr>
        <tr>
            <td colspan="3">CERTIFICACIÓN EXPEDIDA</td>
            <td class="text-center">{{ $acta->certificacion_expedida ? 'X' : '' }}</td>
            <td class="text-center">{{ $acta->certificacion_expedida ? '' : 'X' }}</td>
            <td colspan="5">{{ $acta->certificacion_expedida }}</td>
        </tr>
    </table>

    {{-- Factura asociada --}}
    <table>
        <tr>
            <td colspan="10" class="fw-bold">OBSERVACIONES GENERALES: FACTURA ELECTRÓNICA DE VENTA No. {{ explode('-', $acta->factura->numero)[1] ?? $acta->factura->numero }}</td>
        </tr>
    </table>

    {{-- Firmas --}}
    <div class="title-section">PARA CONSTANCIA DE LO ANTERIOR, INTERVIENEN:</div>
    <table>
        <tr class="bg-gray">
            <td class="text-center fw-bold">ENTIDAD/EMPRESA</td>
            <td class="text-center fw-bold">NOMBRE</td>
            <td class="text-center fw-bold">CARGO</td>
            <td class="text-center fw-bold">EN CALIDAD DE</td>
            <td class="text-center fw-bold">FIRMA</td>
        </tr>
        <tr>
            <td>{{ $acta->contrato->proveedor->nombre ?? '—' }}</td>
            <td>{{ $acta->nombre_entrega }}</td>
            <td>{{ $acta->cargo_entrega }}</td>
            <td>{{ $acta->en_calidad_de }}</td>
            <td></td>
        </tr>
        <tr>
            <td>AGENCIA LOGÍSTICA DE LAS FUERZAS MILITARES</td>
            <td>{{ $acta->user->name ?? '—' }}</td>
            <td>Administrador {{ $acta->dependencia->name ?? '' }}</td>
            <td>REPRESENTANTE DE LA ALFM</td>
            <td></td>
        </tr>
    </table>
</body>
</html>
