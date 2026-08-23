# Plan: Snapshots Históricos de Pagos

## Objetivo

Crear snapshots históricos de registros y movirubros al momento de cada pago, para que al consultar un pago antiguo se vean los saldos de esa fecha, no los actuales.

---

## Problema

Cuando se consulta un pago antiguo, los saldos de `movirubros` ya cambiaron (otros pagos los descontaron). Se necesita preservar el estado de registros y rubros al momento de la creación/confirmación del pago.

---

## Solución

### Tablas nuevas

#### `pagodetaregistros` — Snapshot de registros

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | Autoincremental |
| `pago_id` | FK → pagos | Pago al que pertenece |
| `registro_id` | FK → registros | Registro original |
| `numero_reg` | varchar(50) | Número del registro |
| `valor_reg` | double | Valor del registro |
| `fecha_reg` | date | Fecha del registro |
| `estado` | boolean | Estado (activo/inactivo) |
| `newplazoejecucion` | date | Plazo de ejecución |
| `tiporegistro_id` | FK → tiporegistros | Tipo de registro |
| `timestamps` | | created_at, updated_at |

#### `pagodeterubros` — Snapshot de movirubros

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | Autoincremental |
| `pago_id` | FK → pagos | Pago al que pertenece |
| `movirubro_id` | FK → movirubros | Movirubro original |
| `registro_id` | FK → registros | Registro asociado |
| `rubro_id` | FK → rubros | Rubro asociado |
| `valor_rubro` | double | Valor del rubro |
| `saldo_rubro` | double | Saldo al momento del snapshot |
| `dependencia_afectacion` | varchar nullable | Dependencia de afectación |
| `timestamps` | | created_at, updated_at |

---

## Modelos

### `Pagodetaregistro`
- `$table = 'pagodetaregistros'`
- `$fillable`: pago_id, registro_id, numero_reg, valor_reg, fecha_reg, estado, newplazoejecucion, tiporegistro_id
- Relationships: `pago()`, `registro()`, `tiporegistro()`

### `Pagodeterubro`
- `$table = 'pagodeterubros'`
- `$fillable`: pago_id, movirubro_id, registro_id, rubro_id, valor_rubro, saldo_rubro, dependencia_afectacion
- Relationships: `pago()`, `movirubro()`, `registro()`, `rubro()`

### `Pago` (agregado)
- `registrosSnapshot()` → HasMany(Pagodetaregistro)
- `rubrosSnapshot()` → HasMany(Pagodeterubro)

---

## Flujo de ejecución

### Al guardar pago (estado `abierto`)

```
1. Crear Pago con datos básicos
2. Crear DetallePagos (facturas seleccionadas)
3. Incrementar consecutivo cansecu_pagos
```

### Al confirmar pago (estado `cerrado`)

```
1. Re-sincronizar detalles
2. Calcular deducciones por movirubro
3. Validar saldos suficientes
4. Por cada movirubro afectado:
   - Actualizar saldo_rubro en movirubros (descuento real)
5. Crear snapshot post-descuento:
   - Obtener TODOS los movirubros del contrato (con registro y rubro)
   - Por cada registro único → crear en pagodetaregistros (firstOrCreate, evita duplicados)
   - Por cada movirubro → crear en pagodeterubros (saldo_rubro leído de DB post-descuento)
6. Cambiar facturas a estado 'pagada'
7. Cerrar pago (estado 'cerrado', fecha_cierre = now())
```

### Al editar pago (estado `abierto`)

```
1. Eliminar detalles existentes
2. Recrear detalles con los nuevos datos
```

Nota: los snapshots NO se tocan al editar. Se eliminarán y recrearán al confirmar.

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_07_26_140000_create_pagodetaregistros_table.php` | **Nueva** |
| `database/migrations/2026_07_26_140100_create_pagodeterubros_table.php` | **Nueva** |
| `app/Models/Pagodetaregistro.php` | Completado ($fillable, $table, relationships) |
| `app/Models/Pagodeterubro.php` | Completado ($fillable, $table, relationships) |
| `app/Models/Pago.php` | Agregadas relaciones registrosSnapshot() y rubrosSnapshot() |
| `resources/views/components/pagos/pago-crear.blade.php` | Método crearSnapshot() con firstOrCreate para registros únicos, saldo_rubro post-descuento. Llamado en confirmarPago() después de descuentos |
| `resources/views/components/pagos/pago-editar.blade.php` | Método crearSnapshot() con firstOrCreate para registros únicos, saldo_rubro post-descuento. Llamado en confirmarPago() después de descuentos |
| `AGENTS.md` | Documentación actualizada |

---

## Uso de los snapshots

Los snapshots se crean **al confirmar** el pago, por lo que reflejan el estado **post-descuento** de los rubros:

```php
$pago = Pago::find($id);

// Todos los registros al momento de confirmar el pago
$pago->registrosSnapshot;

// Todos los rubros con sus saldos post-descuento al momento de confirmar
$pago->rubrosSnapshot;

// Saldo específico de un rubro en ese pago (post-descuento)
$pago->rubrosSnapshot->where('movirubro_id', $movirubroId)->first()->saldo_rubro;
```

---

## Decisiones de diseño

1. El snapshot captura **todos los movirubros del contrato**, no solo los afectados por el pago
2. Se captura **solo al confirmar** (cerrado), después de aplicar los descuentos. **NO** se crea al guardar (abierto)
3. Al editar, se eliminan los snapshots existentes y se recrean al confirmar
4. `saldo_rubro` en el snapshot refleja el saldo **post-descuento** (leído de DB después del descuento)
5. Las FKs usan `cascadeOnDelete` para limpiar snapshots si se elimina el pago
6. **Registros únicos**: se usa `firstOrCreate` con `[pago_id, registro_id]` para evitar duplicados cuando varios movirubros apuntan al mismo registro
7. Se eliminó `actualizarSnapshotSaldo()`: ya no se necesita porque el snapshot se crea una sola vez con los saldos correctos
