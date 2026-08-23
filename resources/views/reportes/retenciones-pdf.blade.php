<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Retenciones</title>
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
        .color-general { color: #0284c7; }
        .color-parafiscal { color: #d97706; }
        .color-territorial { color: #059669; }
        .color-retenido { color: #dc2626; }
        .col-contrato { width: 12%; }
        .col-proveedor { width: 14%; }
        .col-num { width: 5%; }
        .col-fecha { width: 8%; }
        .col-cant { width: 5%; }
        .col-valor { width: 8%; }
        .col-ret { width: 7%; }
        .col-total { width: 9%; }
    </style>
</head>
<body>
    <h1>Reporte de Retenciones</h1>
    <div class="info">
        Período: {{ $fechaInicio ?? 'Inicio' }} al {{ $fechaFin ?? 'Fin' }}
        — Generado: {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="resumen">
        <div class="resumen-box">
            <div class="resumen-label">Facturas</div>
            <div class="resumen-value">{{ number_format($resumen->total_facturas) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">General</div>
            <div class="resumen-value color-general">${{ number_format($resumen->ret_general, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Parafiscal</div>
            <div class="resumen-value color-parafiscal">${{ number_format($resumen->ret_parafiscal, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Territorial</div>
            <div class="resumen-value color-territorial">${{ number_format($resumen->ret_territorial, 2) }}</div>
        </div>
        <div class="resumen-box">
            <div class="resumen-label">Total Retenido</div>
            <div class="resumen-value color-retenido">${{ number_format($resumen->sum_retenciones, 2) }}</div>
        </div>
    </div>

    @if ($tab === 'contrato')
        <h2>Por Contrato</h2>
        <table>
            <colgroup>
                <col style="width:12%"><col style="width:14%"><col style="width:5%"><col style="width:8%"><col style="width:8%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:8%"><col style="width:9%">
            </colgroup>
            <thead>
                <tr>
                    <th>Contrato</th>
                    <th>Proveedor</th>
                    <th class="text-center">Fact.</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">IVA</th>
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
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['proveedor_nombre'] }}</td>
                        <td class="text-center">{{ $fila['total_facturas'] }}</td>
                        <td class="text-right">${{ number_format($fila['sum_subtotal'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['sum_iva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['retefuente'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteiva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteica'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['fedepapa'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['asohofrucol'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['estampilla'], 2) }}</td>
                        <td class="text-right bold color-retenido">${{ number_format($fila['total_retenciones'], 2) }}</td>
                        <td class="text-right bold">${{ number_format($fila['sum_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center">No hay datos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif ($tab === 'proveedor')
        <h2>Por Proveedor</h2>
        <table>
            <colgroup>
                <col style="width:20%"><col style="width:6%"><col style="width:9%"><col style="width:9%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:9%">
            </colgroup>
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th class="text-center">Fact.</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">IVA</th>
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
                        <td>{{ $fila['nombre'] }}</td>
                        <td class="text-center">{{ $fila['total_facturas'] }}</td>
                        <td class="text-right">${{ number_format($fila['sum_subtotal'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['sum_iva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['retefuente'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteiva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteica'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['fedepapa'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['asohofrucol'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['estampilla'], 2) }}</td>
                        <td class="text-right bold color-retenido">${{ number_format($fila['total_retenciones'], 2) }}</td>
                        <td class="text-right bold">${{ number_format($fila['sum_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center">No hay datos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <h2>Por Factura</h2>
        <table>
            <colgroup>
                <col style="width:8%"><col style="width:8%"><col style="width:13%"><col style="width:10%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%"><col style="width:7%">
            </colgroup>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Contrato</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">IVA</th>
                    <th class="text-right">Retefuente</th>
                    <th class="text-right">Reteiva</th>
                    <th class="text-right">Reteica</th>
                    <th class="text-right">Fedepapa</th>
                    <th class="text-right">Asohofrucol</th>
                    <th class="text-right">Estampilla</th>
                    <th class="text-right bold">Total Ret.</th>
                    <th class="text-right bold">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datos as $fila)
                    <tr>
                        <td>{{ $fila['numero'] }}</td>
                        <td>{{ $fila['fecha'] }}</td>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['contrato_num'] }}</td>
                        <td class="text-right">${{ number_format($fila['sum_subtotal'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['sum_iva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['retefuente'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteiva'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['reteica'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['fedepapa'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['asohofrucol'], 2) }}</td>
                        <td class="text-right">${{ number_format($fila['estampilla'], 2) }}</td>
                        <td class="text-right bold color-retenido">${{ number_format($fila['total_retenciones'], 2) }}</td>
                        <td class="text-right bold">${{ number_format($fila['sum_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="text-center">No hay datos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
