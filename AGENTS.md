# AGENTS.md — Contexto del Proyecto Pagos por Contrato

> **Leer este archivo al inicio de cada sesión** para entender el proyecto.

---

## Descripción General

Sistema de gestión de **pagos por contrato** para una empresa que solo **compra** bienes o servicios (nunca vende). El pago es **por contra** (no por hito).

**Stack:** Laravel + Livewire 4 + Volt (componentes inline en `.blade.php`)

---

## Arquitectura

### Convenciones del proyecto
- **Tablas:** en plural (`contratos`, `proveedors`, `factura_lineas`)
- **Modelos:** en singular (`Contrato`, `Proveedor`, `FacturaLinea`)
- **Componentes Livewire Volt:** inline en `resources/views/components/<carpeta>/<archivo>.blade.php`
- **Rutas:** registradas en `routes/web.php` con `Route::livewire(...)`
- **Sidebar:** `resources/views/components/app/sidebar.blade.php`

---

## Sistema de Roles y Permisos

### Paquete
**Spatie Laravel-Permission** v8.3.0 (`config/permission.php`)

### Roles
| Rol | Permisos | Descripción |
|-----|----------|-------------|
| `admin` | Todos los 11 permisos | Administrador con acceso total |
| `usuario` | Ninguno | Usuario estándar (asignado por defecto al registrarse) |

### Permisos (11 total, uno por grupo del sidebar)
| Permiso | Grupo | Descripción |
|---------|-------|-------------|
| `dashboard.view` | Dashboard | Acceder al panel principal |
| `otros.view` | Otros | Gestionar catálogos auxiliares |
| `proveedores.view` | Proveedores | Gestionar proveedores |
| `contratos.view` | Contratos | Gestionar contratos, productos y asignaciones |
| `facturar.view` | Facturar | Crear facturas, listar facturas y actas |
| `pagos.view` | Pagos | Gestionar pagos y su impresión |
| `tramite.view` | Trámite de Pagos | Gestionar trámites y plantilla documentos |
| `informes.view` | Informes | Gestionar informes, obligaciones, riesgos |
| `registros.view` | Registros | Gestionar registros, adiciones y reducciones |
| `reportes.view` | Reportes | Ver reportes de contratos, facturación, retenciones y pagos |
| `admin.manage-roles` | Administración | Gestionar roles, permisos y asignar roles a usuarios |

### Implementación
- **Sidebar:** cada grupo envuelto en `@can('permiso.view')` / `@endcan`
- **Ruta admin:** `Route::livewire('admin/roles', 'admin.roles')->middleware('permission:admin.manage-roles')`
- **Middleware registrado** en `app/Http/Kernel.php`: `permission` → `Spatie\Permission\Middleware\PermissionMiddleware`
- **Usuarios nuevos** reciben rol `usuario` por defecto (`app/Actions/Fortify/CreateNewUser.php` → `->assignRole('usuario')`)
- **Seeders**: `PermissionSeeder` crea 11 permisos + roles admin/usuario. `UserSeeder` crea usuario admin con `->assignRole('admin')`. `DatabaseSeeder` ejecuta `PermissionSeeder` primero

### Módulo Admin (`/admin/roles`)
- **Pestaña "Roles y Permisos"**: CRUD de roles con checkboxes de permisos
- **Pestaña "Asignar Roles a Usuarios"**: lista de usuarios con botón para asignar/cambiar rol (dropdown)
- **Protegido**: solo usuarios con permiso `admin.manage-roles` pueden acceder

---

### Cadena de datos principal

```
Contrato (1) ──< (N) Movirubro          (presupuesto: valor y saldo)
     |                                         |
     └──< (N) Itemcontrato ──> (1) Producto   (productos asignados con valor)
                |                              |
                └──> Proveedor                 └──> Municipio (nullable, solo servicios)
                      |
                      └──> RegimenTributario ──< Retencion (via regimen_retencion)
```

### Módulos principales

| Módulo | Ruta | Componente |
|--------|------|------------|
| Contratos | `/contratos/*` | `contratos/contrato.blade.php` |
| Productos | `/contratos/productos` | `contratos/productos.blade.php` |
| **Importar Productos** | `/contratos/importar-productos` | `contratos/importar-productos.blade.php` |
| Asignar productos | `/contratos/asignar-productos` | `contratos/asignar-productos.blade.php` |
| **Facturación (crear)** | `/contratos/facturacion` | `contratos/facturacion.blade.php` |
| **Facturación (listar)** | `/contratos/facturas` | `contratos/facturas-lista.blade.php` |
| **Facturación (editar)** | `/contratos/facturas/{id}/editar` | `contratos/factura-editar.blade.php` |
| **Facturación (PDF)** | `/contratos/facturas/{id}/pdf` | `FacturaPdfController@show` |
| **Facturar (nuevo)** | `/contratos/facturar` | `contratos/facturar.blade.php` |
| Proveedores | `/proveedores/*` | `proveedors/proveedors.blade.php` |
| Retenciones | `/proveedores/retenciones` | `retenciones/retenciones.blade.php` |
| Régimen tributario | `/proveedores/regimen_tributario` | `regimen_tributario/regimen_tributario.blade.php` |
| Tarifas | `/otros/*` | `tarifas/*.blade.php` |
| Municipios | `/otros/municipios` | `municipios/municipios.blade.php` |
| **Dependencias** | `/otros/dependencias` | `dependencias/dependencias.blade.php` |
| Registros | `/registros/*` | `registros/*.blade.php` |
| Reportes facturación | `/reportes/facturacion` | `reportes/facturacion.blade.php` |
| **Reportes retenciones** | `/reportes/retenciones` | `reportes/retenciones.blade.php` |
| **Reportes contratos** | `/reportes/contratos` | `reportes/reporte-contratos.blade.php` |
| **Reportes pagos retenciones** | `/reportes/pagos-retenciones` | `reportes/reporte-pagos-retenciones.blade.php` |
| **Pagos (listar)** | `/pagos` | `pagos/crearpagos.blade.php` |
| **Pagos (crear)** | `/pagos/crear` | `pagos/pago-crear.blade.php` |
| **Pagos (editar)** | `/pagos/{id}/editar` | `pagos/pago-editar.blade.php` |
| **Trámites de pago** | `/tramite-pagos` | `tramite-pagos/⚡tramite-pago.blade.php` |
| **Plantilla documentos** | `/tramite-pagos/plantilla-documentos` | `tramite-pagos/⚡plantilla-documentos.blade.php` |
| **Acta de Recibo** | `/contratos/actas` | `contratos/actas.blade.php` |
| **Acta (editar)** | `/contratos/actas/{id}` | `contratos/acta-editar.blade.php` |
| **Importar Riesgos** | `/informes/importar-riesgos` | `riesgo/importar-riesgos.blade.php` |
| **Admin Roles/Permisos** | `/admin/roles` | `admin/roles.blade.php` |
| **Admin Backups** | `/admin/backups` | `admin/backups.blade.php` |

---

## Sistema de Retenciones

### Concepto clave
Las retenciones se calculan **por cada línea de factura** (no en el catálogo).

### Tres familias de retenciones

| Familia | Depende de | Ejemplos | Fuente de datos |
|---------|-----------|----------|-----------------|
| **General (tributaria)** | Proveedor (régimen) + naturaleza línea | Retefuente, Reteiva, Reteica | `proveedor.retencionesAplicables` (Derivación A) |
| **Parafiscal** | El producto en sí | Fedepapa, Asohofrucol | Pivote `producto_retencion` |
| **Territorial** | **Selección manual por línea** (dropdown) | Estampilla Magdalena | Tabla `estampilla_tarifas` + `factura_lineas.estampilla_retencion_id` |

### Reglas clave
- El **municipio va por LÍNEA** (una factura puede tener ítems en lugares distintos)
- **Reteica** es municipal (depende del municipio)
- **Estampilla** es **selección manual** por línea (NO automática por departamento). Se selecciona desde un dropdown en la línea o desde el selector global de la factura
- Las parafiscales se **asignan manualmente por producto** (checkboxes)
- Se persiste el **% aplicado** en cada retención (histórico inmutable)
- **Selectores globales**: Municipio y Estampilla tienen selects en el encabezado de la factura que actualizan TODAS las líneas al cambiar. Cada línea puede override individualmente

### Derivación A (proveedor → retenciones)
```php
$proveedor->retencionesAplicables; // Collection<Retencion>
// Si tiene_excepcion_retenciones = true → usa pivote proveedor_retencion
// Si es false → deriva del regimen_tributario
```

### Retenciones
`app/Services/CalculadoraRetenciones.php`:
- `calcular(FacturaLinea $linea)` → retorna `['calculadas' => [...], 'pendientes' => [...]]`
- `calcularYPersistir(FacturaLinea $linea)` → calcula y guarda en `factura_linea_retenciones`
- Reteica bien: usa `resolverReteicaBien($proveedor, $linea)` → prioridad: tarifa específica del proveedor (proveedor_id + municipio_id + tipo='bien') > tarifa genérica (proveedor_id IS NULL)
- Reteica servicio: usa `resolverReteicaServicio($proveedor, $linea)` → busca tarifa por `proveedor_id + municipio_id`
- Estampilla: usa `obtenerTerritoriales($linea)` → solo si `linea.estampilla_retencion_id` está definido (selección manual)

---

## Sistema de Facturación

### Facturación
- **Crear**: `/contratos/facturacion` → `facturacion.blade.php`
- **Listar**: `/contratos/facturas` → `facturas-lista.blade.php`
- **Editar**: `/contratos/facturas/{id}/editar` → `factura-editar.blade.php`
- **Reportes**: `/reportes/facturacion` → `reportes/facturacion.blade.php`

### Flujo de creación
1. Usuario busca contrato por número
2. Se muestra saldo disponible: `SUM(movirubros.saldo_rubro)`
3. Se muestran itemcontratos del contrato
4. Usuario selecciona cuáles incluir en la factura
5. Usuario ingresa: número factura, fecha, municipio por defecto, **estampilla por defecto**, **dependencia/comedor (requerido)**
6. Por cada línea: cantidad, tipo_adquisicion (bien/servicio), municipio, estampilla
7. Sistema calcula: valor_base, valor_iva, retenciones automáticamente
8. Totales se calculan sumando líneas

### Reglas de facturación
- **Múltiples facturas por contrato** (el contrato tiene un valor grande que se va descontando)
- **Saldo del contrato:** `SUM(movirubros.saldo_rubro)` — se actualiza al facturar
- **Los valores unitarios NO son editables** (vienen del itemcontrato)
- **Número de factura:** formato `{numero}-{año}`, único por proveedor
- **Totales se calculan automáticamente** sumando las líneas
- **Edición permitida** solo en estado "borrador"
- **Anti doble clic**: usar propiedad `$guardando` para evitar creaciones duplicadas
- **Validación visible**: usar `session()->flash('error', ...)` en vez de `$this->validate()` para mostrar alertas

### Estados de factura
- `borrador`: se puede editar
- `emitida`: no se puede editar (solo consultar)
- `anulada`: no se puede editar. Retenciones se conservan para historial

### Fórmulas
```
valor_base = valor_costo × cantidad
valor_iva = valor_iva_unit × cantidad
valor_con_iva = valor_con_iva_unit × cantidad (= valor_base + valor_iva)
subtotal = suma(valor_base de todas las líneas)
total_iva = suma(valor_iva de todas las líneas)
total_retenciones = suma(valor_retenido de todas las retenciones)
total = suma(valor_con_iva de todas las líneas) - total_retenciones
```

---

## Sistema de Actas de Recibo

### Módulos
- **Listar/Buscar**: `/contratos/actas` → `contratos/actas.blade.php`
- **Crear/Editar**: `/contratos/actas/{id}` → `contratos/acta-editar.blade.php`

### Flujo
1. Usuario busca contrato por número
2. Se muestran facturas **emitidas** del contrato que NO tengan acta creada
3. Se muestran actas ya creadas del contrato (con opción a editar)
4. Usuario selecciona una factura → se abre formulario del acta
5. Se cargan automáticamente: proveedor, dependencia, productos (líneas de factura), totales
6. Usuario completa: nombre quien entrega, cargo, en calidad de, hora, observaciones
7. Guardar → número = `{cansecu_actas + 1}-{year}` → se incrementa `contrato.cansecu_actas`

### Tabla `actas`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `numero` | string | `{consecutivo}-{year}` por contrato |
| `factura_id` | FK | Factura asociada |
| `contrato_id` | FK | Para queries rápidas |
| `dependencia_id` | FK nullable | Lugar/Comedor |
| `nombre_entrega` | string | Nombre de quien entrega |
| `cargo_entrega` | string | Cargo (ej: Despachador) |
| `en_calidad_de` | string | "En calidad de..." |
| `fecha` | date | Fecha del acta |
| `hora` | time | Hora del acta |
| `inspeccion_visual` | string nullable | Observación (opcional) |
| `informes_laboratorio` | string nullable | Observación (opcional) |
| `certificacion_expedida` | string nullable | Observación (opcional) |
| `user_id` | FK | Usuario que crea |

### Reglas
- Solo facturas con `estado = 'emitida'` pueden tener acta
- **NO existe tabla `detalleactas`**: los productos se leen de `factura->lineas`
- El consecutivo es **por contrato** (`contrato.cansecu_actas`)
- Datos de proveedor, productos y totales se heredan de la factura

---

## Sistema de Pagos

### Módulos
- **Listar**: `/pagos` → `crearpagos.blade.php`
- **Crear**: `/pagos/crear` → `pago-crear.blade.php`
- **Editar**: `/pagos/{id}/editar` → `pago-editar.blade.php`

### Flujo de creación
1. Usuario busca contrato por número (Enter o botón)
2. Se muestra: datos del contrato, consecutivos (pago, informe, trámite), tabla de rubros con saldos
3. Usuario selecciona fecha de pago
4. Usuario hace clic en "Agregar Facturas" → modal con facturas emitidas disponibles
5. Tabla de facturas agrupadas por uso: N° Factura, Fecha, Uso, Valor
6. "Guardar Pago" → crea pago en estado `abierto` (solo incrementa `cansecu_pagos`)
7. "Confirmar Pago" → muestra modal de confirmación → cambia a `cerrado` y descuenta saldos

### Flujo de edición
1. Carga pago existente con sus detalles
2. Permite agregar/quitar facturas (mismo modal que crear)
3. "Guardar Cambios" → actualiza detalles dentro de transacción
4. "Confirmar Pago" → cierra y descuenta saldos

### Estados de pago
- `abierto`: editable, se pueden agregar/quitar facturas
- `cerrado`: confirmado, descuenta saldos de rubros, facturas cambian a `pagada`
- `anulada`: no se puede modificar

### Deducción de saldos (al confirmar)
```
Por cada detalle_pago:
  rubro_id → buscar movirubro del contrato
  saldo_rubro -= valor_con_iva (de cada línea)
  movirubro.update(['saldo_rubro' => nuevo saldo])
  factura.estado = 'pagada'
```

### Exclusividad de facturas
- Una factura **no puede** estar en más de un pago (excepto pagos anulados)
- `facturasDisponiblesProperty` excluye facturas en `detalle_pagos` de pagos no anulados
- En editar, se excluyen facturas de otros pagos pero se conservan las del pago actual

### Datos en detalle_pago
Cada fila en la tabla de facturas agrupadas por uso guarda:
- `factura_id` → factura asociada
- `valor_pagado` → suma de valor_con_iva de las líneas de ese uso
- `movirubro_id` → del itemcontrato (primera línea del grupo)
- `uso_id` → del producto (primera línea del grupo)
- `rubro_id` → del producto (primera línea del grupo)

### Consecutivos
- `cansecu_pagos`, `cansecu_infor`, `cansecu_tramite` en tabla `contratos`
- Se incrementan al **crear** (no al confirmar)
- Solo `cansecu_pagos` se persiste al guardar el pago

### Rutas
```php
Route::livewire('pagos', 'pagos.crearpagos')->name('pagos.lista');
Route::livewire('pagos/crear', 'pagos.pago-crear')->name('pagos.crear');
Route::livewire('pagos/{id}/editar', 'pagos.pago-editar')->name('pagos.editar');
```

### Sidebar
Grupo "Pagos" con sub-item "Crear Pagos" → apunta a `pagos.lista`
Grupo "Contratos" con sub-items: Obligación, Importar Obligaciones, Riesgos, Importar Riesgos, Crear Informe

---

## Modelos Principales

### Contrato
- Tabla: `contratos`
- Campos: `numcontrato` (número del contrato), `cansecu_pagos`, `cansecu_infor`, `cansecu_tramite` (consecutivos)
- Relaciones: `proveedor()`, `itemcontratos()`, `movirubros()`, `facturas()`, `registros()`
- computed: `saldo` (suma saldo_rubro), `valorTotal` (suma valor_rubro)

### Itemcontrato
- Tabla: `itemcontratos`
- Campos: `valor_costo`, `iva`, `valor_iva`, `valor_con_iva`, `unidad`
- Relaciones: `contrato()`, `producto()`, `movirubro()`, `rubro()`, `facturaLineas()`

### Producto
- Tabla: `productos`
- Campos: `name`, `tipo` (bien|servicio), `uso_id`, `rubro_id`, `regional_id`, `es_agricola`, `municipio_id` (nullable FK a municipios, solo para servicios)
- Relaciones: `uso()`, `rubro()`, `regional()`, `municipio()`, `retencionesParafiscales()`

### Proveedor
- Tabla: `proveedors`
- Campos: `es_declarante`, `tiene_excepcion_retenciones`
- Relaciones: `regimenTributario()`, `retencionesExcepcion()`, `contratos()`, `reteicaTarifas()`
- Accessor: `retencionesAplicables` (Derivación A)

### Retencion
- Tabla: `retenciones`
- Campos: `name`, `tipo` (general|parafiscal|territorial), `aplica_base`, `aplica_iva`, `divisor`
- Relaciones: `regimenes()`, `proveedores()`, `productos()`, `retencionTarifas()`, `estampillaTarifas()`

### Factura
- Tabla: `facturas`
- Estados: `borrador`, `emitida`, `anulada`
- Relaciones: `proveedor()`, `contrato()`, `municipio()`, `dependencia()`, `lineas()` ← **ordenado por `id`**
- Método estático: `siguienteNumero($proveedorId, $year)` → genera `{num}-{year}`

### FacturaLinea
- Tabla: `factura_lineas`
- Campos: `tipo_adquisicion`, `valor_base`, `valor_iva`, `valor_con_iva`, `cantidad`, `estampilla_retencion_id` (nullable FK a retenciones)
- Relaciones: `factura()`, `itemcontrato()`, `producto()`, `municipio()`, `estampillaRetencion()`, `retenciones()`

### FacturaLineaRetencion
- Tabla: `factura_linea_retenciones` ← **IMPORTANTE: debe tener `$table` explícito**
- Campos: `base_calculo`, `porcentaje_aplicado`, `valor_retenido`
- Relaciones: `facturaLinea()`, `retencion()`

### Dependencia
- Tabla: `dependencias`
- Campos: `name`, `direccion` (nullable), `municipio_id` (nullable FK), `regional_id` (nullable FK)
- Relaciones: `municipio()`, `regional()`
- **Nota**: es un catálogo informativo para etiquetar facturas (no vincula usuarios)

### Pago
- Tabla: `pagos`
- Estados: `abierto` (editable, facturas seleccionables), `cerrado` (confirmado, descuenta saldos), `anulada`
- Relaciones: `contrato()`, `informe()`, `tramitePago()`, `user()`, `detalles()`, `facturas()` (hasManyThrough via DetallePago), `registrosSnapshot()` (HasMany Pagodetaregistro), `rubrosSnapshot()` (HasMany Pagodeterubro)
- Método estático: `siguienteNumero($contratoId, $year)` → genera `{num}-{year}`

### DetallePago
- Tabla: `detalle_pagos`
- Campos: `pago_id`, `factura_id`, `valor_pagado`, `movirubro_id`, `uso_id`, `rubro_id`
- Relaciones: `pago()`, `factura()`, `movirubro()`, `uso()`, `rubro()`
- **Nota**: `movirubro_id`, `uso_id`, `rubro_id` se obtienen de la primera línea de la factura agrupada por uso

### Informe
- Tabla: `informes`
- Campos: `cansecu_infor` (string(5)), `fecha`, `total_info`, `saldo_viene`, `porcentaje_cumplimiento`, `mes_ejecucion` (string(2)), `corresponde_texto_periodo`, `novedad`, `fiducia`, `infopersonal`, `infoaiu`, `anexos`, `recomendacion`, `estado` (abierto/cerrado/anulado)
- Relaciones: `contrato()`, `user()`, `pagos()`, `informeobligaciones()`, `informeriesgos()`

### TramitePago
- Tabla: `tramite_pagos`
- Relaciones: `contrato()`, `user()`, `pagos()`, `informes()`
- **Relaciones documentos**: `documentos()`, `documentosSoporte()`, `documentosExpediente()` ← HasMany a `TramitePagoDocumento`
- **Carga de documentos en `edit()`**: usa `orderBy('id')` para mantener orden de inserción

---

## Módulo de Informes (`/informes/informes`)

### Flujo de creación
1. Usuario ingresa número de contrato → se muestra tabla de informes existentes ordenados por fecha descendente
2. Se calcula automáticamente: `cansecu_infor` = último consecutivo + 1, `fecha` = hoy, `total_info` = suma pagos cerrados de ese consecutivo, `saldo_viene` = suma `total_info` de todos los informes anteriores, `%` = `(saldo_viene + total_info) / valorTotal * 100`
3. **Validación de meses faltantes**: si `mesEjecucion` tiene un mes anterior sin informe, se muestra error con botón "Crear informes faltantes"
4. **Creación masiva**: crea informes para todos los meses faltantes con `total_info=0`, fecha = último día del mes (ajustado por fin de semana), `saldo_viene` = suma `total_info` de informes anteriores, `%` calculado. Luego mueve los pagos al consecutivo del último informe creado

### Campos por defecto al crear
- Novedad = "N/A", Fiducia = "N/A", InfoPersonal = "El servicio fue desarrollado por el personal asignado por la empresa sin novedad especial", InfoAIU = "Ninguna", Anexos = "Ninguno", Recomendacion = "Ninguna"
- Label: "Correspondiente a" (dropdown con meses disponibles)

### Botones de acción
- **Editar**: habilitado solo si estado ≠ anulado
- **Imprimir**: presente en todos los informes (placeholder)
- **Eliminar**: presente en todos, pero `confirmDelete()` solo permite eliminar el último consecutivo

### Tabla de informes
- Columnas: N° (cansecu_infor), Fecha, Correspondiente a, Pagos del informe (suma valor_total de pagos agrupados por ese consecutivo), Total informe ($total_info), Saldo anterior ($saldo_viene), % Cumplimiento
- Ordenados por `fecha` descendente
- Paginación de 10

---

## Sistema de Backups (`/admin/backups`)

### Paquete
**Spatie Laravel-Backup** v10.3.1 (`config/backup.php`)

### Paquete
**Spatie Laravel-Backup** v10.3.1 (`config/backup.php`)

### Tabla `settings`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | PK |
| `key` | string unique | Nombre de la configuración |
| `value` | text nullable | Valor de la configuración |
| `timestamps` | | created_at, updated_at |

### Configuraciones almacenadas
| Key | Default | Descripción |
|-----|---------|-------------|
| `backup_enabled` | `1` | Habilitar/deshabilitar backups automáticos |
| `backup_time` | `02:00` | Hora del backup diario (formato HH:MM) |
| `backup_email` | `''` | Email de notificación (opcional) |

### Rutas
| Ruta | Nombre | Descripción |
|------|--------|-------------|
| `GET /admin/backups` | `admin.backups` | UI de configuración de backups |
| `GET /admin/backups/download/{fileName}` | `admin.backups.download` | Descargar un backup específico |
| `GET /admin/backups/download-latest` | `admin.backups.download-latest` | Descargar el último backup |
| `GET /admin/backups/descargar-fuente` | `admin.backups.descargar-fuente` | Descargar solo código fuente (.zip) |
| `GET /admin/backups/descargar-base-datos` | `admin.backups.descargar-base-datos` | Descargar solo base de datos (.sql) |

### Modelos
- **Setting** (`app/Models/Setting.php`): método estático `get($key, $default)` y `set($key, $value)`

### Seeders
- **SettingSeeder** (`database/seeders/SettingSeeder.php`): crea valores por defecto `backup_enabled=1`, `backup_time=02:00`, `backup_email=''`
- **DatabaseSeeder**: incluye `SettingSeeder`

### UI — Dos pestañas

**Pestaña "Backup Manual":**
- Botón **Descargar Codigo Fuente** (verde) → ruta `descargar-fuente` → crea zip con código fuente excluyendo vendor/node_modules/.git/storage/logs
- Botón **Descargar Base de Datos** (azul) → ruta `descargar-base-datos` → mysqldump directo → descarga .sql
- Botón **Crear Backup Completo (BD + Fuente)** (violeta) → ejecuta `php artisan backup:run` via `exec()` → crea zip con BD + fuente en `storage/app/Pagos_Proveedores/`
- Botón **Limpiar Backups Antiguos** (gris) → ejecuta `php artisan backup:clean`
- Tabla de backups existentes con: Nombre, Fecha (timezone Bogotá), Tamaño, Descargar, Eliminar
- Modal de confirmación para eliminar

**Pestaña "Backup Automatico":**
- Toggle habilitar/deshabilitar
- Input hora del backup (type="time")
- Email de notificación
- Instrucciones para Windows (install-scheduler.bat) y Linux (cron job)

### Configuración `config/backup.php`
- `database_dump_compressor`: `null` (gzip no disponible en Windows)
- `skip_ssl`: `true` (en `config/database.php` dump section)
- `relative_path`: `base_path()` (rutas relativas en el zip, no absolutas)
- Retención: 7 días todos, 30 días diarios, 12 semanas, 12 meses, 2 años
- Notificaciones mail: deshabilitadas (todas en `[]`)

### Configuración `config/database.php`
```php
'dump' => [
    'dump_binary_path' => env('DB_DUMP_BINARY', 'D:/wamp64/bin/mysql/mysql8.4.7/bin'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'useSingleTransaction' => true,
    'skip_ssl' => true,
],
```

### Variables `.env`
- `DB_DUMP_BINARY="D:/wamp64/bin/mysql/mysql8.4.7/bin"` — ruta de mysqldump
- `BACKUP_NOTIFICATION_EMAIL` — email de notificaciones (opcional)

### Windows Task Scheduler
- Archivo `install-scheduler.bat` en raíz del proyecto
- Opción [1]: Windows — crea tarea `PagosXContrato_Backup` que ejecuta `php artisan schedule:run` cada minuto
- Opción [2]: Linux — muestra instrucciones del cron job para cPanel/Plesk

### Programación automática (`app/Console/Kernel.php`)
- Lee settings `backup_enabled` y `backup_time` de BD
- Si habilitado: `$schedule->command('backup:run')->dailyAt($backupTime)`
- Try-catch por si la tabla settings no existe

### Archivos excluidos del zip de fuente
- `vendor/`, `node_modules/`, `.git/`, `storage/framework/`, `storage/logs/`, `storage/app/`, `.env`

---

## Tablas de soporte

| Tabla | Propósito |
|-------|-----------|
| `municipios` | Municipios con departamento (para Reteica y estampillas) |
| `dependencias` | Dependencias/comedores con name, direccion (nullable), municipio_id (FK), regional_id (FK) |
| `retencion_tarifas` | Tarifas parametrizables por retención general |
| `reteica_tarifas` | Tarifa Reteica servicio por proveedor+municipio |
| `estampilla_tarifas` | Tarifas estampillas por departamento |
| `producto_retencion` | Pivote: parafiscales asignadas a cada producto |
| `regimen_retencion` | Pivote: retenciones por régimen tributario |
| `proveedor_retencion` | Pivote: excepciones de retenciones por proveedor |
| `pagos` | Pagos por contrato (estado: abierto/cerrado/anulada) |
| `detalle_pagos` | Detalle de cada factura en un pago (con movirubro_id, uso_id, rubro_id) |
| `pagodetaregistros` | Snapshot histórico de registros al momento del pago (numero_reg, valor_reg, fecha_reg, estado, newplazoejecucion, tiporegistro_id) |
| `pagodeterubros` | Snapshot histórico de movirubros al momento del pago (valor_rubro, saldo_rubro, dependencia_afectacion) |
| `informes` | Informes de gestión (pueden tener múltiples pagos) |
| `tramite_pagos` | Trámites de pago (pueden tener múltiples informes) |
| `tramite_pago_documentos` | Documentos soporte y expediente de cada trámite (nombre, fecha, valor, folio, reposa_expediente) |
| `settings` | Configuraciones clave-valor (backup_enabled, backup_time, backup_email) |

### Archivos de idioma
| Archivo | Propósito |
|---------|-----------|
| `lang/es/auth.php` | Mensajes de autenticación |
| `lang/es/pagination.php` | Paginación |
| `lang/es/passwords.php` | Restablecimiento de contraseñas |
| `lang/es/validation.php` | Mensajes de validación |
| `lang/es.json` | Traducciones de vistas (login, registro, perfil, API, paginación) |

---

## Datos iniciales (Seeders)

### Retenciones
| Nombre | Tipo | Base |
|--------|------|------|
| Retefuente | general | base |
| Reteica | general | base |
| Reteiva | general | iva |
| Fedepapa | parafiscal | base |
| Asohofrucol | parafiscal | base |
| Estampilla Magdalena | territorial | base |

### Tarifas de retención
| Retención | Declarante | Tipo | Agrícola | % |
|-----------|-----------|------|----------|---|
| Retefuente | false | – | – | 3.5 |
| Retefuente | true | servicio | – | 4 |
| Retefuente | true | bien | true | 1 |
| Retefuente | true | bien | false | 2 |
| Reteiva | – | – | – | 15 |
| Reteica | – | bien | – | 0.5 |
| Fedepapa | – | – | – | 1 |
| Asohofrucol | – | – | – | 1 |

---

## Estado de implementación

### ✅ Implementado
- Base de datos completa (migraciones para facturación y retenciones)
- Modelos: Factura, FacturaLinea, FacturaLineaRetencion + todos los de soporte
- Servicio CalculadoraRetenciones funcional
- UI de creación de facturas (wizard original: `/contratos/facturacion`)
- **UI de creación de facturas (nuevo: `/contratos/facturar`)** — flujo completo: buscar contrato → ver saldo → seleccionar productos → calcular retenciones → guardar
- UI de edición de facturas (`/contratos/facturas/{id}/editar`) con agregar/eliminar líneas
- UI de listado de facturas (`/contratos/facturas`) con acciones: editar, emitir, anular
- UI de reportes de facturación (3 pestañas)
- **Reporte de retenciones** (`/reportes/retenciones`) con 3 pestañas (Por Contrato, Por Proveedor, Por Factura)
- **Exportar retenciones a Excel** (maatwebsite/excel con `RetencionesExport`)
- **Exportar retenciones a PDF** (barryvdh/laravel-dompdf, vista `reportes/retenciones-pdf.blade.php`)
- CRUDs de catálogos (retenciones, tarifas, municipios, etc.)
- Selector global de estampilla en encabezado de factura (actualiza todas las líneas)
- Selector global de municipio en encabezado de factura (actualiza todas las líneas)
- Campo `estampilla_retencion_id` en `factura_lineas` (FK a retenciones)
- Campo `tipo` (bien/servicio) en productos
- Reteica servicio: tarifa por proveedor+municipio con `tipo_adquisicion` (bien/servicio)
- Reteica bien: tarifa por municipio regional con `tipo_adquisicion` = 'bien'. Prioridad: específica del proveedor > genérica (proveedor_id IS NULL)
- Regional con `municipio_id` (para Reteica bien)
- **Anti doble clic**: propiedad `$guardando` + botón deshabilitado con "Creando..." en creación de facturas
- **Validación con alertas**: mensajes flash de error visibles (número, fecha, líneas)
- **Total sin retenciones**: mostrar en resumen de creación y edición (subtotal + IVA)
- **Orden de líneas**: `Factura::lineas()` ordenado por `id` para mantener orden de inserción
- **Número de factura**: muestra solo la parte numérica (`001`) en UI, sin prefijo de proveedor ni año
- **Módulo de Pagos**: CRUD completo (listar, crear, editar)
- **Modelos**: Pago, DetallePago, Informe, TramitePago
- **Migraciones**: pagos, detalle_pagos, informes, tramite_pagos + add_columns_to_detalle_pagos
- **Sidebar**: Grupo "Pagos" con sub-item "Crear Pagos"
- **Rutas**: pagos.lista, pagos.crear, pagos.editar
- **Crear pago**: búsqueda de contrato, consecutivos, tabla de rubros, modal de facturas, guardar (abierto), confirmar (cerrado con descuento de saldos)
- **Editar pago**: carga existente, agregar/quitar facturas, guardar cambios, confirmar
- **Exclusividad de facturas**: facturas en pagos no anulados se excluyen de disponibles
- **Detalle con movirubro_id, uso_id, rubro_id**: tracking para reportes y deducción de saldos
- **Logo personalizado**: sidebar y login usan `public/images/logo.png` con fondo circular blanco
- **Idioma español**: config/app.php locale='es', traducciones en `lang/es/` y `lang/es.json`
- **Campo `valor_con_iva`** en `factura_lineas`: almacena el total de la línea (base + IVA) para simplificar cálculos
- **Reporte de retenciones corregido**: usa subquery para evitar duplicación por JOIN en totales por contrato/proveedor
- **Reporte de contratos** (`/reportes/contratos`): colores por estado del saldo (verde ≥75%, naranja 50-74%, rojo <50%)
- **Listado de pagos**: muestra `numcontrato` del contrato (campo correcto)
- **Número de factura en pagos**: muestra solo la parte numérica (`001`), no el código completo
- **Reporte de pagos con retenciones** (`/reportes/pagos-retenciones`): tabla detallada por factura con pivoteo de retenciones, columna "Total Sin Retenciones" (subtotal + IVA), columnas NIT proveedor y N° Registro, Total Neto = subtotal + IVA − retenciones, exportar a Excel y PDF
- **Campo `valor_con_iva`** en `cargarFactura` de `facturar.blade.php`: se carga correctamente al editar facturas existentes (antes no se asignaba y mostraba $0)
- **Campos requeridos identificados** en formulario de proveedores: asterisco rojo `*` en Nombre, NIT, Email, Tipo Persona, Régimen Tributario y Tipo Cuenta
- **Columna "V. c/IVA"** en tabla de líneas de facturar: muestra el valor unitario con IVA (`valor_con_iva_unit`) al lado de IVA Unit.
- **Colores en títulos de columnas** de facturar: azul para columnas de BD (Producto, V. Unitario, IVA Unit., V. c/IVA), verde para columnas calculadas (Subtotal, IVA Total, Total)
- **Select de productos buscable** en facturar: componente Alpine.js inline con filtro por nombre, sin dependencia de `Alpine.data()`, con `wire:ignore` para evitar reseteo por re-render de Livewire
- **Select de contratos buscable** en reporte pagos retenciones: componente Alpine.js inline con filtro por número de contrato o nombre de proveedor, con `wire:ignore`
- **Snapshots históricos de pagos**: tablas `pagodetaregistros` y `pagodeterubros` que guardan el estado de registros y movirubros al momento de **confirmar** el pago (post-descuento). Se crean UNA vez al confirmar (estado `cerrado`), no al guardar (abierto). Registros únicos con `firstOrCreate` (evita duplicados cuando varios movirubros apuntan al mismo registro). `saldo_rubro` se lee de DB post-descuento. Modelos: `Pagodetaregistro`, `Pagodeterubro`. Relaciones en `Pago`: `registrosSnapshot()`, `rubrosSnapshot()`
- **Bug corregido: descuento triple de saldo en confirmar pago**: al confirmar un pago, si una factura tenía múltiples detalles (agrupados por uso), el código iteraba TODAS las líneas de la factura por CADA detalle, descontando el saldo múltiples veces. Corregido en `pago-crear.blade.php` y `pago-editar.blade.php` con `$pago->detalles->pluck('factura')->unique('id')` para deduplicar antes de iterar líneas
- **Saldo corregido en BD**: contrato `010-009-2026` (ID=1) tenía saldo_rubro=$12,664,050 por el bug de descuento triple. Corregido a $18,221,350 ($21,000,000 − $2,778,650)
- **Bug corregido: estados en reporte facturación**: el template solo manejaba `borrador` y `emitida`, todo lo demás mostraba "Anulada". Agregado caso `pagada` con badge sky en `reportes/facturacion.blade.php:189-197`
- **Módulo de Informes** (`/informes/informes`): CRUD completo con validación de meses faltantes, creación masiva, y cálculo de % cumplimiento
- **Validación de meses faltantes**: al intentar crear informe, si hay meses anteriores sin informe se bloquea con alerta y botón "Crear informes faltantes"
- **Creación masiva de informes faltantes**: crea informes con `total_info=0`, `saldo_viene` acumulado desde la BD, `%` calculado, y fecha = último día del mes (ajustado por fin de semana). Cada informe copia obligaciones del contrato (`confirmar='NO'`) y riesgos. Mueve pagos al consecutivo del último informe creado
- **Cálculo de % cumplimiento**: `ejecutado = saldo_viene + total_info`, `% = ejecutado / valorTotal * 100`. `saldo_viene` = `SUM(informes.total_info)` de informes anteriores (NO usa saldo del contrato)
- **`saldo_viene` en informes**: suma de `total_info` de todos los informes anteriores (no anulados). Se persiste en BD para consulta histórica. En meses faltantes, `saldo_viene` se acumula desde la BD
- **Delete de informes**: botón visible en todos, pero solo permite eliminar el último consecutivo. Al eliminar, retrocede pagos con `cansecu_infor >= eliminado` usando `DB::raw('cansecu_infor - 1')`, luego borra `informeobligaciones`, `informeriesgos` e `informeregistros`
- **Botón imprimir**: placeholder para reporte futuro (`imprimirInforme($id)`)
- **Módulo de Dependencias/Comedores** (`/otros/dependencias`): CRUD completo con selects de municipio, regional y campo dirección
- **Campo `dependencia_id` en facturas**: FK nullable a `dependencias`, requerido al facturar
- **Selector de dependencia en facturar**: dropdown en sección "Datos de la Factura" (junto a MIGO, Fecha MIGO y Estampilla), requerido
- **Seeders de municipios y dependencias**: `MunicipioSeeder` (13 registros), `DependenciaSeeder` (17 registros)
- **Municipio con regional**: campo `regional_id` (FK nullable) en tabla `municipios`. Modelo `Municipio` con relación `regional()`. Seeder actualizado con `regional_id = 1` para todos
- **Dependencias filtradas por regional**: en componentes `facturar.blade.php` y `facturacion.blade.php`, el select de Dependencia/Comedor filtra por `regional_id` del usuario autenticado. Si el usuario no tiene regional, muestra todas (fallback)
- **Módulo de Actas de Recibo** (`/contratos/actas`): buscar contrato → facturas emitidas sin acta → crear acta con datos heredados de la factura
- **Tabla `actas`**: numero, factura_id, contrato_id, dependencia_id, nombre_entrega, cargo_entrega, en_calidad_de, fecha, hora, inspeccion_visual, informes_laboratorio, certificacion_expedida, user_id
- **Consecutivo de actas**: `contrato.cansecu_actas` (bigInteger, default 0). Se incrementa al crear acta. Formato `{consecutivo}-{year}` por contrato
- **Sin tabla `detalleactas`**: los productos del acta se leen de `factura->lineas` (no hay duplicación de datos)
- **Solo facturas emitidas**: el listado de facturas disponibles para acta filtra por `estado = 'emitida'`
- **Actas totales sin retenciones**: el acta (editar y listado) muestra solo Subtotal, IVA y Total (subtotal + IVA). NO muestra retenciones (diferente a la factura que sí las muestra)
- **Módulo de Importar Riesgos** (`/informes/importar-riesgos`): importación masiva de riesgos desde Excel, misma estructura que Importar Obligaciones
- **Import RiesgosImport** (`app/Imports/RiesgosImport.php`): usa `ToCollection` + `WithHeadingRow`. Columnas: Tipo (nullable), Descripción (requerido), Tratamiento (requerido), Responsable (requerido), Periodicidad (nullable). Duplicados por `contrato_id + descripcion + tratamiento`
- **Export RiesgosPlantillaExport** (`app/Exports/RiesgosPlantillaExport.php`): genera Excel con 5 columnas y 2 filas de ejemplo
- **Controlador RiesgosController** (`app/Http\Controllers\RiesgosController.php`): método `plantillaExcel()` para descargar plantilla
- **Ruta descarga plantilla**: `GET informes/importar-riesgos/plantilla` → `importar.riesgos.plantilla`
- **Sidebar renombrado**: sub-item "Productos" → "Productos x Rubro y Uso" en grupo Contratos
- **Módulo de Importar Productos** (`/contratos/importar-productos`): importación masiva de productos desde Excel
- **Import ProductosImport** (`app/Imports/ProductosImport.php`): usa `ToCollection` + `WithHeadingRow` + `WithValidation`. Valida que `codigo_uso` exista en tabla `usos`. Omite productos duplicados (mismo nombre + uso). Cuenta creados/omitidos/errores
- **Export ProductosPlantillaExport** (`app/Exports/ProductosPlantillaExport.php`): genera Excel con 2 filas de ejemplo y encabezados: Código Uso, Nombre Producto, Tipo, Es Agrícola, Municipio (opcional)
- **Controlador ProductosController** (`app/Http\Controllers/ProductosController.php`): método `plantillaExcel()` para descargar plantilla (necesario porque Livewire Volt no permite retornar respuestas HTTP)
- **Ruta descarga plantilla**: `GET contratos/importar-productos/plantilla` → `importar.productos.plantilla`
- **Plantilla Excel**: columnas `codigo_uso`, `nombre_producto`, `tipo` (bien/servicio), `es_agricola` (sí/no). El rubro se asigna automáticamente desde el uso
- **Lógica de import**: busca uso por `codigo_uso` → si no existe, ERROR. Verifica duplicados (nombre + uso_id) → si existe, OMITE. Crea producto con `rubro_id` del uso
- **Sistema de Roles y Permisos**: Spatie Laravel-Permission v8.3.0 con 11 permisos (uno por grupo del sidebar), 2 roles (admin con todos, usuario sin ninguno), middleware `permission` registrado en Kernel, sidebar envuelto en `@can` directives, protección de ruta admin con `middleware('permission:admin.manage-roles')`
- **Módulo Admin** (`/admin/roles`): pestaña Roles y Permisos (CRUD con checkboxes de permisos) + pestaña Asignar Roles a Usuarios (dropdown por usuario)
- **Sidebar corregido**: faltaba `</div>` de cierre del `<div class="min-w-fit">` que causaba pantalla negra en desktop
- **Usuario admin en seeders**: `PermissionSeeder` crea roles+permisos, `UserSeeder` crea usuario admin con `->assignRole('admin')`, `DatabaseSeeder` ejecuta `PermissionSeeder` primero
- **Sistema de Backups**: Spatie Laravel-Backup v10.3.1 con UI en `/admin/backups`. Backup manual (descargar fuente .zip, descargar BD .sql, crear backup completo BD+fuente), backup automático configurable (hora, habilitar/deshabilitar), tabla de backups existentes con descargar/eliminar, retención automática (7/30/12/12), `install-scheduler.bat` para Windows Task Scheduler
- **Tabla `settings`**: store de configuraciones clave-valor (`backup_enabled`, `backup_time`, `backup_email`) con modelo `Setting` y seeder `SettingSeeder`
- **Bug corregido: 405 Method Not Allowed en facturar**: el `<form wire:submit="buscarContrato">` no usaba `.prevent`, causando submission nativa POST a ruta GET-only. Corregido a `wire:submit.prevent`
- **Bug corregido: fecha en PDF de informes**: templates `pdf_informe.blade.php` y `pdf_informe_comedores.blade.php` usaban `$data->fechainfo` (no existe), corregido a `$data->fecha`. Antes `new DateTime(null)` siempre mostraba fecha de hoy
- **Diferenciación de rubros en facturar**: tabla de rubros muestra columna `#` con fondo amarillo y badge `DUP #N` para rubros duplicados (mismo rubro_id en múltiples movirubros)
- **Saldo de rubro en selector de productos (facturar)**: dropdown de productos muestra saldo del rubro debajo del código en verde, para que el usuario sepa de cuál movirubro viene cada producto. Aplica en modo normal y ajuste
- **Validación de saldo de rubros al grabar factura (facturar)**: `grabarFactura()` valida que el total `valor_con_iva` de las líneas agrupadas por itemcontrato no exceda el saldo disponible del movirubro. Considera facturas existentes (borrador + emitida) del mismo rubro. Excluye factura actual al editar
- **Validación de saldo de rubros al crear/editar factura (facturacion)**: `crearFactura()` y `editarFactura()` incluyen la misma validación de saldo de rubros. En `editarFactura()` se excluye la factura actual del cálculo
- **Columna Rubro en tablas de productos (facturacion)**: ambas tablas (modo Normal y modo Ajuste) muestran columna "Rubro" con código + nombre y saldo del movirubro en verde
- **Eager loading corregido en facturacion**: `buscarContrato()` ahora carga `itemcontratos.movirubro` y `itemcontratos.rubro` para evitar N+1 queries
- **Programación automática**: `app/Console/Kernel.php` lee `backup_enabled` y `backup_time` de BD, ejecuta `backup:run` diario si habilitado (try-catch por si tabla no existe)
- **Configuración Windows**: `DB_DUMP_BINARY` en `.env` apunta a `D:/wamp64/bin/mysql/mysql8.4.7/bin`, `config/database.php` dump section con `skip_ssl => true`, `config/backup.php` con `relative_path => base_path()` para rutas relativas en zip, notificaciones mail deshabilitadas

### ❌ Pendiente
- Gestión de estados completa (borrador → emitida → pagada → anulada)
- Relación `Proveedor::facturas()` (falta en el modelo)
- Paginación en reportes de retenciones
- **Reporte de imprimir informe**: definir formato y lógica para `imprimirInforme()`

### Trámite de Pago - Word Template

**Plantilla Word:** `public/Formatos/GF-FO-36-Plantilla.docx`

**Placeholders en la plantilla:**
- `{{doc_soporte_nombre_N}}` - Nombre del documento soporte (fila N)
- `{{doc_soporte_fecha_N}}` - Fecha del documento soporte
- `{{doc_soporte_valor_N}}` - Valor del documento soporte
- `{{doc_soporte_folio_N}}` - Folio del documento soporte
- `{{doc_exp_reposa_N}}` - Expediente reposa (X o vacío)
- `{{doc_exp_fecha_N}}` - Fecha del expediente
- `{{doc_exp_folio_N}}` - Folio del expediente
- `{{doc_exp_informe_consecutivo}}` - Consecutivo del informe (cansecu_infor + 1)

**Datos de documentos soporte (guardados en `tramite_pago_documentos`):**
- Index 0: Control de pagos GF-FO-35 → fecha y valor del primer pago
- Index 1: Factura, cuenta de cobro → números de factura en nombre, fecha, valor total, folio
- Index 4: MIGO/MB51 → números de migo en nombre, fecha, valor

**Datos de documentos expediente:**
- Index 8 (fila 9): "Informe de supervisión {cansecu_infor + 1}" → se guarda con consecutivo

**Servicios Word:**
- `PlantillaWordService` (`app/Services/PlantillaWordService.php`) - servicio activo
- Reemplaza placeholders en `word/document.xml` via DOMDocument/DOMXPath
- Carga documentos con `orderBy('id')` para mantener orden

**Método `llenarDocumentosConFacturas()`:**
- Consulta directa a BD (no depende de propiedades computadas)
- Se ejecuta en `store()` y `update()` antes de `saveDocumentos()`
- Llena fecha, valor, folio y nombres en documentos_soporte
- Llena nombre con consecutivo en documentos_expediente index 8

**Delete de trámite:** elimina también los registros de `tramite_pago_documentos`

---

## Decisiones de diseño (no reabrir)

1. El cálculo va en la **línea de factura**, no en el catálogo
2. Generales del proveedor (Derivación A), parafiscales del producto, territoriales del municipio
3. Las tarifas son **datos editables**, no código
4. Reteica servicio se captura una vez por proveedor+municipio
4a. Reteica bien permite proveedor específico (opcional). Si existe tarifa del proveedor, se usa. Si no, fallback a la genérica
5. Se persiste el % aplicado (histórico inmutable)
6. El municipio va por **línea** (no por factura)
7. Parafiscales se asignan manualmente por producto (checkboxes)
8. La factura se **crea seleccionando itemcontratos** del contrato
9. `cantidad` vive en `factura_lineas`, NO en `itemcontratos`
10. **Múltiples facturas por contrato**
11. **Saldo del contrato** se calcula desde `movirubros.saldo_rubro`
12. Los **valores unitarios NO son editables** en la factura
13. **Número de factura:** formato `{numero}-{año}`, único por proveedor
14. **Totales se calculan automáticamente** sumando las líneas
15. **Edición permitida** en estado "borrador"
16. **Estampilla es selección manual** por línea (no automática por departamento). Se guarda como `estampilla_retencion_id` en `factura_lineas`
17. **Selectores globales** en el encabezado de factura: Municipio y Estampilla. Cambiar el global actualiza TODAS las líneas. Cada línea puede tener override individual
18. **Edición de factura**: permite agregar y eliminar líneas (no solo modificar). Productos disponibles se filtran por itemcontratos del contrato que no estén ya en la factura
19. **Estampilla_retencion_id**: siempre se convierte a `null` cuando está vacío (nunca cadena vacía `''`) para evitar errores SQL en columnas integer
20. **Número de factura en UI**: se muestra solo la parte numérica (`001`), NO el código interno completo (`1-001-2026`). Se extrae con `explode('-', $factura->numero)[1]` en todos los componentes
21. **El pago va por contra** (no por hito). El saldo se descuenta del movirubro rubro
22. **Facturas en pago**: se agrupan por uso en la tabla. Cada grupo = un detalle_pago
23. **movirubro_id, uso_id, rubro_id** se guardan en `detalle_pagos` para tracking y reportes
24. **Exclusividad de facturas**: una factura no puede estar en más de un pago (excepto pagos anulados)
25. **Confirmar pago**: descuenta saldos de movirubros y cambia facturas a `pagada`
26. **Guardar pago**: crea en estado `abierto`, solo incrementa consecutivo. El descuento de saldos es solo al confirmar
27. **Edición de pago**: permite modificar facturas mientras esté en estado `abierto`
27a. **Flujo de saldo_rubro en pagos**: Guardar (abierto) = NO toca saldo. Confirmar (cerrado) = descuenta saldo_rubro. Anular (anulada) = devuelve saldo_rubro. El reporte de contratos solo refleja saldos de pagos confirmados
28. **cargarPago en editar**: lee directamente de `detalle_pagos` (con `uso_id`) para evitar duplicados al recargar
28. **cargarPago en editar**: lee directamente de `detalle_pagos` (con `uso_id`) para evitar duplicados al recargar
29. **`valor_con_iva` en factura_lineas**: se almacena directamente para simplificar cálculos en reportes y pagos. Se calcula como `itemcontrato.valor_con_iva × cantidad`
30. **Reportes de retenciones**: usan subquery separada para totales de factura (subtotal, IVA) para evitar duplicación por JOIN con `factura_linea_retenciones`
31. **Colores en reporte de contratos**: verde (≥75% saldo), naranja (50-74%), rojo (<50%) con leyenda visual encima del reporte
32. **Número de factura en UI**: siempre se muestra solo la parte numérica (`001`), NO el código completo. Se extrae con `explode('-', $factura->numero)[1]`
33. **Reportes de pagos retenciones**: Total Neto = `subtotal + total_iva − total_retenciones` (NO `valor_con_iva`). Joins: `proveedors` (NIT), `movirubros` → `registros` (N° Registro) desde `detalle_pagos.movirubro_id`
34. **Snapshots históricos de pagos**: al guardar un pago (abierto), se captura el estado de TODOS los movirubros del contrato en `pagodeterubros` y sus registros en `pagodetaregistros`. Al confirmar (cerrado), se actualizan los `saldo_rubro` de los snapshots afectados. Esto permite consultar pagos antiguos y ver los saldos de esa fecha
35. **Snapshots post-descuento**: los snapshots se crean SOLO al confirmar el pago (estado `cerrado`), no al guardar (abierto). `saldo_rubro` se lee de DB post-descuento. Registros se deduplican con `firstOrCreate` para evitar duplicados cuando varios movirubros apuntan al mismo registro
36. **Confirmar pago: deduplicar facturas antes de iterar**: cuando una factura tiene múltiples detalles (agrupados por uso), el código de confirmación debe iterar facturas `unique('id')` para no descontar el saldo varias veces. Bug encontrado y corregido el 2026-08-01
37. **% cumplimiento de informes**: se calcula desde lo informado (`saldo_viene + total_info`), NO desde el saldo del contrato. El saldo del contrato ya refleja pagos confirmados, así que usarlo daría resultados incorrectos para informes anteriores
38. **Meses faltantes**: se validan al crear informe. Si faltan meses anteriores, se bloquea creación y se ofrece botón "Crear informes faltantes" que los crea masivamente con `total_info=0`
39. **Creación masiva de informes faltantes**: fecha = último día del mes (ajustado por fin de semana), `saldo_viene` = suma `total_info` de informes anteriores (acumulativo), `%` calculado. Se mueven pagos al consecutivo del último informe creado
40. **Delete de informes**: botón visible en todos los informes, pero `confirmDelete()` solo permite eliminar el último consecutivo (compara `cansecu_infor` con `contrato.cansecu_infor`). Al eliminar, retrocede pagos con `cansecu_infor >= eliminado` en 1 posición (via `DB::raw('cansecu_infor - 1')`), luego borra `informeobligaciones`, `informeriesgos` e `informeregistros`
41. **Documentos soporte en trámite**: se guardan en `tramite_pago_documentos` con datos reales de facturas/migos calculados en `llenarDocumentosConFacturas()`. El template Word usa placeholders `{{doc_soporte_nombre_N}}`, `{{doc_soporte_fecha_N}}`, `{{doc_soporte_valor_N}}`, `{{doc_soporte_folio_N}}`
42. **Orden de documentos**: se mantiene con `orderBy('id')` tanto al cargar en `edit()` como en el servicio Word
43. **Delete de trámite**: elimina también los registros de `tramite_pago_documentos` (no había cascade en FK)
44. **Informe de supervisión**: en fila 9 del expediente, se muestra "Informe de supervisión {cansecu_infor + 1}" tanto en el formulario como en el Word (placeholder `{{doc_exp_informe_consecutivo}}`)
45. **Dependencia en factura**: es un catálogo informativo (`dependencias`). No vincula usuarios. Se selecciona al facturar (campo requerido). FK `dependencia_id` nullable en `facturas`
45a. **Campo dirección en dependencias**: la tabla `dependencias` tiene campo `direccion` (nullable) para almacenar la dirección física de la dependencia/comedor
46. **Dependencia filtrada por regional**: en `facturar.blade.php` y `facturacion.blade.php`, el select de Dependencia/Comedor filtra por `regional_id` del usuario autenticado. Si el usuario no tiene regional, muestra todas (fallback)
47. **Municipio con regional**: tabla `municipios` tiene `regional_id` (FK nullable). Seeder y modelo actualizados. Formulario de municipios incluye select de regional
48. **Acta de Recibo**: NO usa tabla `detalleactas`. Los productos se leen de `factura->lineas`. El acta es un "wrapper" de la factura con metadata adicional (quién entrega, cargo, en calidad de, observaciones)
49. **Consecutivo de actas por contrato**: `contrato.cansecu_actas` (bigInteger, default 0). Se incrementa al crear acta. Formato `{consecutivo}-{year}`
50. **Solo facturas emitidas para actas**: el listado de facturas disponibles filtra por `estado = 'emitida'`
51. **Importar productos**: módulo separado de Productos. Descarga plantilla Excel vía controlador (no Livewire). Import usa `ToCollection` + `WithHeadingRow` + `WithValidation`. El rubro se asigna automáticamente desde el uso. Productos duplicados (mismo nombre + uso) se omiten
52. **Sidebar renombrado**: sub-item "Productos" → "Productos x Rubro y Uso" en grupo Contratos
53. **Actas sin retenciones**: el acta (editar y listado) muestra solo Subtotal, IVA y Total (subtotal + IVA). NO muestra retenciones. Diferente a la factura que sí las muestra
54. **Importar riesgos**: módulo igual a Importar Obligaciones. Columnas: Tipo (nullable), Descripción, Tratamiento, Responsable (requeridos), Periodicidad (nullable)
55. **Roles y permisos**: un permiso por grupo del sidebar (no por ítem individual). Control solo de UI (ocultar menús), no de rutas/URLs (excepto admin/manage-roles que sí bloquea ruta)
56. **Sidebar con @can**: cada grupo del sidebar envuelto en `@can('permiso.view')` / `@endcan`. El grupo Admin se envuelve en `@can('admin.manage-roles')`. Duplicados por `contrato_id + descripcion + tratamiento`
57. **Backups**: spatie/laravel-backup con `relative_path => base_path()` para incluir BD + fuente en el zip. `database_dump_compressor => null` (gzip no disponible en Windows). Notificaciones mail deshabilitadas. `exec()` para ejecutar artisan desde web (fallback a CLI si está deshabilitado)
58. **Programación backups**: lee `backup_enabled` y `backup_time` de tabla `settings` (no de config). Try-catch por si la tabla no existe. `install-scheduler.bat` crea tarea Windows Task Scheduler
59. **Campo `municipio_id` en productos**: nullable FK a `municipios`. Solo visible/editable para productos tipo "Servicio" (en CRUD de productos). Auto-asigna a `municipio_linea` al facturar (facturacion, facturar, factura-editar). Si el servicio no tiene municipio, se muestra toast de warning y se requiere selección manual. En `ProductosImport` se importa columna opcional "Municipio" (nombre). En `ProductosPlantillaExport` se agrega columna "Municipio (opcional)"
60. **PDF de factura individual**: `FacturaPdfController@show` genera PDF con barryvdh/laravel-dompdf. Vista `reportes/factura-pdf.blade.php`. Ruta `GET contratos/facturas/{id}/pdf` → `facturas.pdf`. Botón de descarga visible en facturas con estado emitida y pagada. Todos los cálculos se hacen en el controlador (no en la vista) para evitar errores de tipo con DomPDF. Muestra: encabezado con logo, datos factura/proveedor/contrato, tabla de productos, retenciones por línea, resumen (subtotal, IVA, total sin retenciones, retenciones, total a pagar), desglose de retenciones agrupado por nombre+porcentaje+municipio con columna de tipo (bien/servicio), y total por porcentaje
61. **Reteica bien con prioridad de proveedor**: `resolverReteicaBien($proveedor, $linea)` busca primero tarifa específica del proveedor (proveedor_id + municipio_id + tipo='bien'). Si no existe, cae en la genérica (proveedor_id IS NULL). CRUD de reteica-tarifas permite seleccionar proveedor para tipo bien (antes forzaba null). Índice único de 3 columnas: `(proveedor_id, municipio_id, tipo_adquisicion)` reemplaza al antiguo de 2 columnas
62. **Migración limpia de índices reteica**: se eliminó el índice único `reteica_tarifas_proveedor_id_municipio_id_unique` (2 columnas) que impedía crear tarifas para mismo proveedor+municipio con tipos diferentes. Solo queda el de 3 columnas `reteica_prov_muni_tipo_UNIQUE`
63. **Bug corregido: saldo_viene en informes**: antes usaba `$contrato->saldo` (saldo restante del contrato), ahora usa `SUM(informes.total_info)` de informes anteriores (no anulados). Fórmula de % cumplimiento corregida a `(saldo_viene + total_info) / valorTotal * 100`
64. **Bug corregido: saldo_viene acumulativo en meses faltantes**: en `confirmarCrearMesesFaltantes()`, `saldoViene` ahora se calcula desde la BD (`SUM(informes.total_info)`) y se acumula correctamente en el loop
65. **Bug corregido: pagos huérfanos al eliminar informes**: el `delete()` ahora retrocede pagos con `cansecu_infor >= eliminado` usando `DB::raw('cansecu_infor - 1')`. Antes solo decrementaba `contrato.cansecu_infor` y los pagos quedaban apuntando a consecutivos inexistentes
66. **Obligaciones y riesgos en informes auto-creados**: al crear meses faltantes, cada informe ahora copia las obligaciones del contrato con `confirmar = 'NO'` y `entregable = 'No se requirió en este periodo'`, y los riesgos del contrato
67. **PDF informes: campo corregido**: templates `pdf_informe.blade.php` y `pdf_informe_comedores.blade.php` usaban `$data->corresponde_periodo` (no existe), corregido a `$data->corresponde_texto_periodo`
68. **Columna Pagos con modal**: la columna "Pagos" del listado de informes muestra un botón con conteo que abre un modal con tabla completa (N° Pago, Fecha, Valor Total). Antes mostraba badges inline que se desbordaban
69. **Pagos por informe sin depender de búsqueda**: la columna de pagos usa `$informe->contrato_id` directamente para que funcione sin buscar el contrato en el filtro superior
70. **`wire:submit.prevent`** en facturar: el formulario de búsqueda de contrato usa `wire:submit.prevent` para evitar submission nativa POST que causa 405 Method Not Allowed
71. **Fecha en PDF de informes**: templates usan `$data->fecha` (columna real), NO `$data->fechainfo` (no existe). `new DateTime(null)` mostraba siempre fecha de hoy
72. **Tabla de rubros con DUP**: se muestra columna `#` y badge `DUP #N` cuando hay múltiples movirubros con el mismo rubro_id, para diferenciarlos visualmente
73. **Saldo visible en selector de productos**: cada producto muestra el saldo del movirubro al que pertenece, para que el usuario sepa de cuál rubro viene y evite exceder saldos
74. **Validación de saldo al facturar**: antes de grabar/emitir factura, se valida que el total `valor_con_iva` por movirubro no exceda el saldo disponible. Considera facturas existentes (borrador + emitida) del mismo rubro. Evita llegar al pago con conflicto de saldo
75. **DUP solo en tabla de rubros, no en productos**: el badge DUP se muestra en la tabla de rubros (facturar) para identificar filas duplicadas. En las tablas de productos NO se muestra DUP — el saldo es suficiente para diferenciar
