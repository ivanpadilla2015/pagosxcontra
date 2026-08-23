# Retenciones por régimen tributario

Este documento describe cómo funciona el sistema de retenciones: el catálogo,
el mapeo régimen ↔ retención, la derivación automática hacia el proveedor
(**Derivación A**) y las excepciones manuales por proveedor.

## Concepto general

Un proveedor **no almacena** sus retenciones por defecto. Las retenciones que le
aplican se **derivan automáticamente** de su régimen tributario. Solo cuando el
proveedor necesita una configuración distinta se activa una **excepción** que
guarda retenciones específicas en un pivote propio.

Actualmente el sistema solo registra **si una retención aplica o no** (sin
porcentajes). El diseño está preparado para añadir porcentajes u otros atributos
más adelante (ver [Puntos de extensión](#puntos-de-extensión)).

## Modelo de datos

| Tabla | Descripción |
|-------|-------------|
| `retenciones` | Catálogo de retenciones (`id`, `name`, `aplica_base`, `aplica_iva`, timestamps). |
| `regimen_tributarios` | Regímenes tributarios existentes. |
| `regimen_retencion` | Pivote **mapeo editable** régimen ↔ retención (`regimen_tributario_id`, `retencion_id`, unique compuesto). |
| `proveedors` | Incluye la columna boolean `tiene_excepcion_retenciones` (default `false`). |
| `proveedor_retencion` | Pivote de **excepciones** proveedor ↔ retención (`proveedor_id`, `retencion_id`, unique compuesto). |

### Modelos y relaciones

- `App\Models\Retencion` (`$table = 'retenciones'`)
  - `regimenes()` → belongsToMany `RegimenTributario`
  - `proveedores()` → belongsToMany `Proveedor`
- `App\Models\RegimenTributario`
  - `retenciones()` → belongsToMany `Retencion` (tabla `regimen_retencion`)
- `App\Models\Proveedor`
  - `regimenTributario()` → belongsTo
  - `retencionesExcepcion()` → belongsToMany `Retencion` (tabla `proveedor_retencion`)
  - `getRetencionesAplicablesAttribute()` → accessor que resuelve la derivación

## CRUD del catálogo de retenciones

Ruta: `proveedores/retenciones` (nombre `retenciones`, enlace en el sidebar bajo
Proveedores). Componente:
`resources/views/components/retenciones/retenciones.blade.php`.

Permite crear, editar y eliminar retenciones. Cada retención tiene:

- `name` (único).
- `aplica_base` (boolean): la deducción se calcula sobre la **base gravable**.
- `aplica_iva` (boolean): la deducción se calcula sobre el **IVA**.

Ambos flags son independientes: una retención puede aplicar sobre la base, sobre
el IVA o sobre ambos. Valores por defecto del seeder: Retefuente y Reteica →
base; Reteiva → IVA.

Al crear una retención nueva aparece automáticamente en:

- los checkboxes del **Régimen Tributario** (para asignarla a los regímenes),
- los checkboxes de **excepción** del formulario de Proveedor.

Al eliminar una retención se limpian sus asignaciones en los pivotes
`regimen_retencion` y `proveedor_retencion` gracias al borrado en cascada.

## Mapeo por defecto (seeders)

Definido en `RegimenRetencionSeeder` (poblado a partir de `RetencionSeeder`):

| Régimen tributario | Retenciones que aplica |
|--------------------|------------------------|
| No Responsable de IVA | Retefuente, Reteica |
| Régimen Simple | Reteiva |
| Responsable de IVA | Retefuente, Reteica, Reteiva |
| Gran Contribuyente | Retefuente, Reteica |
| Autorretenedor | (ninguna) |

Retenciones del catálogo base: **Retefuente**, **Reteica**, **Reteiva**.

## Derivación A

La resolución de las retenciones aplicables a un proveedor se hace en el accessor
`Proveedor::getRetencionesAplicablesAttribute()`:

```php
// Uso
$proveedor->retencionesAplicables; // Illuminate\Support\Collection<Retencion>
```

Regla:

1. Si `tiene_excepcion_retenciones === true` → devuelve las retenciones del pivote
   `proveedor_retencion` (excepción manual).
2. Si `tiene_excepcion_retenciones === false` (caso normal) → devuelve las
   retenciones del régimen tributario asignado (`regimen_retencion`). **No se
   persiste nada** en el proveedor.

De este modo, cambiar el mapeo de un régimen se refleja automáticamente en todos
los proveedores de ese régimen que no tengan excepción.

## Editar el mapeo régimen → retención

En el CRUD de **Régimen Tributario**
(`resources/views/components/regimen_tributario/regimen_tributario.blade.php`):

- El listado muestra una columna con las retenciones asignadas a cada régimen.
- El modal de crear/editar incluye checkboxes de retenciones
  (`retencionesSeleccionadas`).
- Al guardar se sincroniza el pivote con
  `$regimenTributario->retenciones()->sync($this->retencionesSeleccionadas)`.

## Excepciones por proveedor

En el formulario de **Proveedor**
(`resources/views/components/proveedors/proveedors.blade.php`):

- Al seleccionar el régimen (con `wire:model.live`) se muestran las retenciones
  **derivadas** del régimen (badges), calculadas por el computed
  `retencionesDelRegimen()`.
- El interruptor **"Usar retenciones personalizadas (excepción)"** enlaza a
  `tiene_excepcion_retenciones`.
- Con la excepción activa aparecen checkboxes del catálogo completo
  (`retencionesSeleccionadas`).
- Al guardar:
  - Si hay excepción → `retencionesExcepcion()->sync($this->retencionesSeleccionadas)`.
  - Si no → `retencionesExcepcion()->sync([])` (se limpia el pivote y vuelve a
    derivar del régimen).

## Puntos de extensión

- **Porcentajes / atributos por retención**: añadir columnas al catálogo
  `retenciones` (p. ej. `porcentaje`) o a los pivotes con `withPivot()` para que
  el porcentaje dependa del régimen o del proveedor.
- **Base gravable / topes**: extender los pivotes con columnas adicionales y
  ajustar el accessor para incluirlas.
- **Vigencias**: añadir campos de fecha a los pivotes y filtrar por vigencia en
  las relaciones.
