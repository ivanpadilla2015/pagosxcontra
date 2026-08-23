<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Pagos con Retenciones</title>
    <style>
        body { font-family: sans-serif; font-size: 9px; color: #333; margin: 15px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 12px; margin-top: 12px; color: #555; }
        .info { font-size: 10px; color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
        th { background-color: #6b21a8; color: white; padding: 4px 4px; text-align: left; font-size: 7px; word-wrap: break-word; }
        td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; font-size: 7px; word-wrap: break-word; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .resumen { display: flex; gap: 10px; margin: 10px 0; }
        .resumen-box { border: 1px solid #d1d5db; border-radius: 4px; padding: 5px 8px; }
        .resumen-label { font-size: 7px; color: #6b7280; text-transform: uppercase; }
        .resumen-value { font-size: 12px; font-weight: bold; }
        .color-subtotal { color: #374151; }
        .color-iva { color: #374151; }
        .color-retenido { color: #dc2626; }
        .color-neto { color: #059669; }
        .tfoot-row { background-color: #f3f4f6; font-weight: bold; border-top: 2px solid #d1d5db; }
    </style>
</head>
<body>
    <h1>Reporte de Pagos con Retenciones</h1>
    <div class="info">
        @if ($contrato)
            Contrato: {{ $contrato->numcontrato }} — {{ $contrato->proveedor->nombre ?? '' }} |
        @endif
        Período: {{ $fechaInicio ?? 'Inicio' }} al {{ $fechaFin ?? 'Fin' }}
        — Generado: {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="resumen">
        <div class="resumen-box">
            <div class="resumen-label">Facturas</div>
            <div class="resumen-value">{{ number_format($resumen->total_facturas) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Subtotal</div>
            <div class="resumen-value color-subtotal">${{ number_format($resumen->sum_subtotal, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">IVA</div>
            <div class="resumen-value color-iva">${{ number_format($resumen->sum_iva, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Total Sin Ret.</div>
            <div class="resumen-value" style="color:#7c3aed">${{ number_format($resumen->sum_subtotal + $resumen->sum_iva, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Total Retenciones</div>
            <div class="resumen-value color-retenido">${{ number_format($resumen->sum_retenciones, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Total Neto</div>
            <div class="resumen-value color-neto">${{ number_format($resumen->sum_total, 2) }}</div>
        </div>
    </div>

    <table>
        <colgroup>
            <col style="width:6%"><col style="width:6%"><col style="width:5%"><col style="width:10%"><col style="width:6%"><col style="width:6%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%">
        </colgroup>
        <thead>
            <tr>
                <th>N° Pago</th>
                <th>Fecha Pago</th>
                <th>N° Fact.</th>
                <th>Proveedor</th>
                <th>NIT</th>
                <th>N° Reg.</th>
                <th>Fecha Fact.</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">IVA</th>
                <th class="text-right">Total Sin Ret.</th>
                <th class="text-right">Retefuente</th>
                <th class="text-right">Reteiva</th>
                <th class="text-right">Reteica</th>
                <th class="text-right">Fedepapa</th>
                <th class="text-right">Asohofrucol</th>
                <th class="text-right">Estampilla</th>
                <th class="text-right bold">Total Ret.</th>
                <th class="text-right bold">Total Neto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($datos as $fila)
                <tr>
                    <td>{{ $fila['pago_numero'] }}</td>
                    <td>{{ $fila['pago_fecha'] }}</td>
                    <td>{{ $fila['factura_numero'] }}</td>
                    <td>{{ $fila['proveedor_nombre'] }}</td>
                    <td>{{ $fila['proveedor_nit'] }}</td>
                    <td>{{ $fila['numero_reg'] }}</td>
                    <td>{{ $fila['factura_fecha'] }}</td>
                    <td class="text-right">${{ number_format($fila['subtotal'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['iva'], 2) }}</td>
                    <td class="text-right bold" style="color:#7c3aed">${{ number_format($fila['subtotal'] + $fila['iva'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['retefuente'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['reteiva'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['reteica'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['fedepapa'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['asohofrucol'], 2) }}</td>
                    <td class="text-right">${{ number_format($fila['estampilla'], 2) }}</td>
                    <td class="text-right bold color-retenido">${{ number_format($fila['total_retenciones'], 2) }}</td>
                    <td class="text-right bold">${{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="17" class="text-center">No hay datos</td>
                </tr>
            @endforelse
        </tbody>
        @if ($datos->isNotEmpty())
            <tfoot>
                <tr class="tfoot-row">
                    <td colspan="7">Totales</td>
                    <td class="text-right">${{ number_format($datos->sum('subtotal'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('iva'), 2) }}</td>
                    <td class="text-right bold" style="color:#7c3aed">${{ number_format($datos->sum('subtotal') + $datos->sum('iva'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('retefuente'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('reteiva'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('reteica'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('fedepapa'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('asohofrucol'), 2) }}</td>
                    <td class="text-right">${{ number_format($datos->sum('estampilla'), 2) }}</td>
                    <td class="text-right bold color-retenido">${{ number_format($datos->sum('total_retenciones'), 2) }}</td>
                    <td class="text-right bold">${{ number_format($datos->sum('total'), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
