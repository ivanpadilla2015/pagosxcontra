# Plan: Reporte de Pagos con Retenciones

> **Fecha:** 22 de julio de 2026
> **Objetivo:** Crear un reporte detallado de pagos que muestre las retenciones aplicadas a cada factura dentro de cada pago, con exportación a Excel y PDF.

---

## Descripción

Reporte que muestra todas las facturas pagadas (estado `cerrado`) con el desglose completo de retenciones aplicadas a cada una. El reporte indica claramente a qué contrato, pago y factura pertenecen las retenciones.

---

## Filtros

| Filtro | Tipo | Obligatorio | Descripción |
|--------|------|-------------|-------------|
| Contrato | Select | **Sí** | Contrato del cual se quiere el reporte |
| Fecha Inicio | Date | Sí | Fecha de inicio del período |
| Fecha Fin | Date | Sí | Fecha de fin del período |

---

## Columnas de la Tabla

| # | Columna | Descripción | Fuente |
|---|---------|-------------|--------|
| 1 | N° Pago | Número del pago (formato: 001-2026) | `pagos.numero` |
| 2 | Fecha Pago | Fecha en que se confirmó el pago | `pagos.fecha` |
| 3 | N° Factura | Solo numérico (001) | `facturas.numero` → `explode('-', ...)[1]` |
| 4 | Proveedor | Nombre del proveedor | `proveedors.nombre` |
| 5 | Fecha Factura | Fecha de emisión de la factura | `facturas.fecha` |
| 6 | Subtotal | Suma de valor_base de las líneas | Subquery separada |
| 7 | IVA | Suma de valor_iva de las líneas | Subquery separada |
| 8 | **Total Sin Ret.** | **Subtotal + IVA (antes de retenciones)** | **Cálculo** |
| 9 | Retefuente | Valor retenido | CASE WHEN |
| 10 | Reteiva | Valor retenido | CASE WHEN |
| 11 | Reteica | Valor retenido | CASE WHEN |
| 12 | Fedepapa | Valor retenido | CASE WHEN |
| 13 | Asohofrucol | Valor retenido | CASE WHEN |
| 14 | Estampilla | Valor retenido | CASE WHEN |
| 15 | Total Retenciones | Suma de todas las retenciones | SUM |
| 16 | Total Neto | subtotal + iva - retenciones | Cálculo |

---

## Cadena de Consulta (Query Chain)

```
pagos (estado = 'cerrado')
  → JOIN detalle_pagos ON pagos.id = detalle_pagos.pago_id
    → JOIN facturas ON facturas.id = detalle_pagos.factura_id
      → JOIN factura_lineas ON factura_lineas.factura_id = facturas.id
        → JOIN factura_linea_retenciones ON factura_linea_retenciones.factura_linea_id = factura_lineas.id
          → JOIN retenciones ON retenciones.id = factura_linea_retenciones.retencion_id
```

### Subquery para Totales de Factura
Para evitar duplicación por el JOIN con retenciones, se usa una subquery separada:
```php
$invoiceTotals = DB::table('factura_lineas')
    ->join('facturas', 'facturas.id', '=', 'factura_lineas.factura_id')
    ->whereIn('facturas.id', $facturaIds)
    ->select(
        'facturas.id as factura_id',
        DB::raw('SUM(factura_lineas.valor_base) as subtotal'),
        DB::raw('SUM(factura_lineas.valor_iva) as iva'),
        DB::raw('SUM(factura_lineas.valor_con_iva) as total')
    )
    ->groupBy('facturas.id')
    ->keyBy('factura_id');
```

### Pivoteo de Retenciones (CASE WHEN)
```php
DB::raw("SUM(CASE WHEN retenciones.name = 'Retefuente' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as retefuente"),
DB::raw("SUM(CASE WHEN retenciones.name = 'Reteiva' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteiva"),
DB::raw("SUM(CASE WHEN retenciones.name = 'Reteica' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as reteica"),
DB::raw("SUM(CASE WHEN retenciones.name = 'Fedepapa' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as fedepapa"),
DB::raw("SUM(CASE WHEN retenciones.name = 'Asohofrucol' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as asohofrucol"),
DB::raw("SUM(CASE WHEN retenciones.name = 'Estampilla Magdalena' THEN factura_linea_retenciones.valor_retenido ELSE 0 END) as estampilla"),
```

---

## Archivos a Crear

| # | Archivo | Propósito |
|---|---------|-----------|
| 1 | `resources/views/components/reportes/reporte-pagos-retenciones.blade.php` | Componente Livewire Volt |
| 2 | `app/Exports/ReportePagosRetencionesExport.php` | Exportación Excel |
| 3 | `app/Http/Controllers/ReportePagosRetencionesController.php` | Controlador PDF/Excel |
| 4 | `resources/views/reportes/pagos-retenciones-pdf.blade.php` | Template PDF |

## Archivos a Modificar

| # | Archivo | Cambio |
|---|---------|--------|
| 5 | `routes/web.php` | Agregar 3 rutas |
| 6 | `resources/views/components/app/sidebar.blade.php` | Agregar sub-item |
| 7 | `AGENTS.md` | Documentar el reporte |

---

## Rutas

```php
Route::livewire('reportes/pagos-retenciones', 'reportes.reporte-pagos-retenciones')->name('reportes.pagos.retenciones');
Route::get('reportes/pagos-retenciones/excel', [\App\Http\Controllers\ReportePagosRetencionesController::class, 'excel'])->name('reportes.pagos.retenciones.excel');
Route::get('reportes/pagos-retenciones/pdf', [\App\Http\Controllers\ReportePagosRetencionesController::class, 'pdf'])->name('reportes.pagos.retenciones.pdf');
```

---

## Estilos de Colores

| Retención | Color | Clase |
|-----------|-------|-------|
| Retefuente | Azul | `text-sky-600 dark:text-sky-400` |
| Reteiva | Azul | `text-sky-600 dark:text-sky-400` |
| Reteica | Azul | `text-sky-600 dark:text-sky-400` |
| Fedepapa | Ámbar | `text-amber-600 dark:text-amber-400` |
| Asohofrucol | Ámbar | `text-amber-600 dark:text-amber-400` |
| Estampilla | Verde | `text-emerald-600 dark:text-emerald-400` |
| Total Retenciones | Rojo | `text-rose-600 dark:text-rose-400` |

---

## Fórmulas

```
subtotal = SUM(factura_lineas.valor_base) por factura
iva = SUM(factura_lineas.valor_iva) por factura
total_sin_retenciones = subtotal + iva
total_retenciones = SUM(factura_linea_retenciones.valor_retenido) por factura
total_neto = subtotal + iva - total_retenciones
```

---

## Decisiones de Diseño

1. **Contrato obligatorio**: El reporte siempre se genera por contrato específico
2. **Solo pagos cerrados**: Se muestran facturas de pagos confirmados (`estado = 'cerrado'`)
3. **Subquery separada**: Para totales de factura, se usa subquery para evitar duplicación por JOIN con retenciones
4. **Número de factura**: Se muestra solo la parte numérica (`001`), extraída con `explode('-', ...)[1]`
5. **Pivoteo CASE WHEN**: Patrón idéntico al reporte de retenciones existente
6. **Exportación**: Mismas dependencias (maatwebsite/excel, barryvdh/laravel-dompdf)
