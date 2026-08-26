<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura {{ $factura->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 10px; color: #333; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #6b21a8; padding-bottom: 10px; margin-bottom: 15px; }
        .header-left { display: flex; gap: 12px; align-items: center; }
        .logo { width: 70px; height: 70px; }
        .empresa-info h1 { font-size: 16px; color: #6b21a8; font-weight: bold; }
        .empresa-info p { font-size: 9px; color: #666; line-height: 1.4; }
        .factura-titulo { text-align: right; }
        .factura-titulo h2 { font-size: 22px; color: #6b21a8; font-weight: bold; }
        .factura-titulo .estado { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 9px; font-weight: bold; margin-top: 4px; }
        .estado-emitida { background: #dbeafe; color: #1d4ed8; }
        .estado-pagada { background: #d1fae5; color: #059669; }
        .estado-borrador { background: #f3f4f6; color: #6b7280; }
        .estado-anulada { background: #fee2e2; color: #dc2626; }

        .info-grid { display: flex; gap: 20px; margin-bottom: 15px; }
        .info-box { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; background: #f9fafb; }
        .info-box h3 { font-size: 8px; text-transform: uppercase; color: #6b21a8; font-weight: bold; margin-bottom: 4px; letter-spacing: 0.5px; }
        .info-box p { font-size: 10px; color: #333; line-height: 1.5; }
        .info-box .label { color: #6b7280; font-size: 8px; }

        .section-title { font-size: 12px; font-weight: bold; color: #6b21a8; border-bottom: 2px solid #e9d5ff; padding-bottom: 3px; margin: 15px 0 8px 0; }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background-color: #6b21a8; color: white; padding: 5px 6px; text-align: left; font-size: 8px; font-weight: bold; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        .retenciones-cell { background: #fef2f2; }
        .ret-badge { display: inline-block; background: #fee2e2; color: #dc2626; padding: 1px 5px; border-radius: 8px; font-size: 7px; margin: 1px 2px; white-space: nowrap; }

        .resumen-container { display: flex; justify-content: flex-end; margin-top: 15px; }
        .resumen-table { width: 55%; }
        .resumen-table td { padding: 4px 8px; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
        .resumen-table .resumen-label { text-align: right; color: #6b7280; }
        .resumen-table .resumen-value { text-align: right; font-weight: bold; }
        .resumen-table .total-row td { border-top: 2px solid #6b21a8; font-size: 13px; color: #6b21a8; font-weight: bold; padding-top: 6px; }
        .resumen-table .retenciones-row td { color: #dc2626; }
        .resumen-table .neto-row td { border-top: 2px solid #059669; font-size: 14px; color: #059669; font-weight: bold; padding-top: 6px; }

        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="header">
        <div class="header-left">
            @if (file_exists($logoPath))
                <img src="{{ $logoPath }}" class="logo" alt="Logo">
            @endif
            <div class="empresa-info">
                <h1>Pagos por Contrato</h1>
                <p>Sistema de Gesti&oacute;n de Pagos</p>
            </div>
        </div>
        <div class="factura-titulo">
            <h2>FACTURA</h2>
            <span class="estado estado-{{ $factura->estado }}">{{ ucfirst($factura->estado) }}</span>
        </div>
    </div>

    {{-- DATOS PRINCIPALES --}}
    <div class="info-grid">
        <div class="info-box">
            <h3>Datos de la Factura</h3>
            <p><span class="label">N&uacute;mero:</span> <strong>{{ explode('-', $factura->numero)[1] ?? $factura->numero }}</strong></p>
            <p><span class="label">Fecha:</span> {{ $factura->fecha->format('d/m/Y') }}</p>
            @if ($factura->numero_migo)
                <p><span class="label">N&deg; MIGO:</span> {{ $factura->numero_migo }}</p>
            @endif
            @if ($factura->fecha_migo)
                <p><span class="label">Fecha MIGO:</span> {{ $factura->fecha_migo->format('d/m/Y') }}</p>
            @endif
        </div>
        <div class="info-box">
            <h3>Proveedor</h3>
            <p><strong>{{ $factura->proveedor->nombre ?? '-' }}</strong></p>
            <p><span class="label">NIT:</span> {{ $factura->proveedor->nit ?? '-' }}</p>
            @if ($factura->proveedor->regimenTributario)
                <p><span class="label">R&eacute;gimen:</span> {{ $factura->proveedor->regimenTributario->name }}</p>
            @endif
            @if ($factura->proveedor->email)
                <p><span class="label">Email:</span> {{ $factura->proveedor->email }}</p>
            @endif
        </div>
        <div class="info-box">
            <h3>Contrato</h3>
            <p><strong>{{ $factura->contrato->numcontrato ?? '-' }}</strong></p>
            @if ($factura->municipio)
                <p><span class="label">Municipio:</span> {{ $factura->municipio->nombre }} ({{ $factura->municipio->departamento }})</p>
            @endif
            @if ($factura->dependencia)
                <p><span class="label">Dependencia:</span> {{ $factura->dependencia->name }}</p>
            @endif
        </div>
    </div>

    {{-- TABLA DE PRODUCTOS --}}
    <div class="section-title">Detalle de Productos / Servicios</div>
    <table>
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:25%">Producto</th>
                <th style="width:8%">Tipo</th>
                <th style="width:8%" class="text-center">Cant.</th>
                <th style="width:11%" class="text-right">V. Unitario</th>
                <th style="width:7%" class="text-center">IVA %</th>
                <th style="width:11%" class="text-right">Base</th>
                <th style="width:10%" class="text-right">IVA</th>
                <th style="width:12%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineasPdf as $linea)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $linea['producto_nombre'] }}</td>
                    <td>
                        @if ($linea['tipo_adquisicion'] === 'bien')
                            <span style="color:#2563eb; font-weight:bold;">Bien</span>
                        @else
                            <span style="color:#d97706; font-weight:bold;">Servicio</span>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($linea['cantidad'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($linea['valor_costo_unit'], 2, ',', '.') }}</td>
                    <td class="text-center">{{ $linea['iva_unit'] }}%</td>
                    <td class="text-right">${{ number_format($linea['valor_base'], 2, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($linea['valor_iva'], 2, ',', '.') }}</td>
                    <td class="text-right bold">${{ number_format($linea['valor_con_iva'], 2, ',', '.') }}</td>
                </tr>
                @if ($linea['retenciones']->count() > 0)
                    <tr>
                        <td colspan="9" class="retenciones-cell" style="padding: 3px 6px;">
                            @foreach ($linea['retenciones'] as $ret)
                                <span class="ret-badge">
                                    {{ $ret['nombre'] }}: {{ number_format($ret['porcentaje'], 1) }}% = ${{ number_format($ret['valor'], 2, ',', '.') }}
                                </span>
                            @endforeach
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    {{-- RESUMEN --}}
    <div class="resumen-container">
        <table class="resumen-table">
            <tr>
                <td class="resumen-label">Subtotal:</td>
                <td class="resumen-value">${{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="resumen-label">Total IVA:</td>
                <td class="resumen-value">${{ number_format($totalIva, 2, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="resumen-label">Total sin Retenciones:</td>
                <td class="resumen-value">${{ number_format($totalSinRet, 2, ',', '.') }}</td>
            </tr>
            <tr class="retenciones-row">
                <td class="resumen-label">(-) Total Retenciones:</td>
                <td class="resumen-value">-${{ number_format($totalRetenciones, 2, ',', '.') }}</td>
            </tr>
            <tr class="neto-row">
                <td class="resumen-label">Total a Pagar:</td>
                <td class="resumen-value">${{ number_format($totalNeto, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- RESUMEN DE RETENCIONES --}}
    @if ($retencionesAgrupadas->count() > 0)
        <div class="section-title">Resumen de Retenciones</div>
        <table>
            <thead>
                <tr>
                    <th style="width:25%">Retenci&oacute;n</th>
                    <th style="width:12%">Tipo</th>
                    <th style="width:18%" class="text-right">Base de C&aacute;lculo</th>
                    <th style="width:12%" class="text-center">% Aplicado</th>
                    <th style="width:18%" class="text-right">Valor Retenido</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($retencionesAgrupadas as $ret)
                    <tr>
                        <td class="bold">{{ $ret['nombre'] }} {{ number_format($ret['porcentaje'], 1) }}% {{ $ret['municipio'] }}</td>
                        <td>
                            @if ($ret['tipo_adquisicion'] === 'bien')
                                <span style="color:#2563eb; font-weight:bold;">Bien</span>
                            @else
                                <span style="color:#d97706; font-weight:bold;">Servicio</span>
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($ret['base'], 2, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($ret['porcentaje'], 1) }}%</td>
                        <td class="text-right bold" style="color:#dc2626;">${{ number_format($ret['valor'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr style="background:#fef2f2; font-weight:bold;">
                    <td colspan="4" class="text-right" style="border-top:2px solid #dc2626;">Total Retenciones:</td>
                    <td class="text-right" style="color:#dc2626; border-top:2px solid #dc2626;">${{ number_format($totalRetenciones, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- TOTAL POR PORCENTAJE --}}
    @if ($retencionesPorcentaje->count() > 0)
        <div class="section-title" style="margin-top: 20px;">Total por Porcentaje</div>
        <table>
            <thead>
                <tr>
                    <th style="width:20%" class="text-center">% Aplicado</th>
                    <th style="width:50%">Retenciones incluidas</th>
                    <th style="width:30%" class="text-right">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($retencionesPorcentaje as $item)
                    <tr>
                        <td class="text-center bold">{{ $item['porcentaje_label'] }}</td>
                        <td>{{ $item['nombres'] }}</td>
                        <td class="text-right bold" style="color:#dc2626;">${{ number_format($item['valor'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i:s') }} &mdash; Sistema de Gesti&oacute;n de Pagos por Contrato
    </div>

</body>
</html>
