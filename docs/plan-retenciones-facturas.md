# Plan: Motor de Retenciones por Línea de Factura

> **Estado:** PROPUESTA / NO IMPLEMENTADO (pendiente de aprobación del equipo).
> **Fecha:** 2026-07-11
> **Cómo retomar este plan:** Si el equipo lo aprueba, basta con indicar
> *"lee `docs/plan-retenciones-facturas.md` y continúa con la implementación
> desde la Fase X"*. Este documento contiene todo el contexto necesario para
> reanudar sin volver a diseñar desde cero.

---

## 0. Contexto del proyecto (estado actual ya implementado)

Stack: **Laravel + Livewire 4 + Volt** (componentes de página en
`resources/views/components/<carpeta>/<archivo>.blade.php`, registrados en
`routes/web.php` con `Route::livewire(...)`). Convención: tablas en plural,
modelos en singular. Menú lateral en
`resources/views/components/app/sidebar.blade.php`.

Ya existe y funciona:

- **Retenciones** (catálogo): tabla `retenciones` con `name`, `aplica_base`
  (boolean), `aplica_iva` (boolean). Modelo `App\Models\Retencion`
  (`$table = 'retenciones'`), relaciones `regimenes()` y `proveedores()`.
  CRUD en `resources/views/components/retenciones/retenciones.blade.php`.
- **Régimen tributario ↔ retenciones**: pivote `regimen_retencion`. Mapeo
  editable desde el CRUD de régimen.
- **Derivación A (proveedor → retenciones)**: el proveedor NO persiste
  retenciones por defecto; se derivan de su régimen mediante el accessor
  `Proveedor::getRetencionesAplicablesAttribute()`. Si
  `proveedores.tiene_excepcion_retenciones = true`, usa el pivote
  `proveedor_retencion` (excepción manual). **Esta lógica se reutiliza tal cual
  en el motor de retenciones para las retenciones "generales".**
- **Rubros y Usos**: tablas `rubros` (`codigo_rubro`, `nombre_rubro`) y `usos`
  (`codigo_uso`, `nombre_uso`, `rubro_id`). Importación por Excel
  (maatwebsite/excel) en `resources/views/components/rubros/importrubrosusos.blade.php`.
  Listado en `resources/views/components/rubros/listadorubrosusos.blade.php`.
- **Productos** (catálogo): tabla `productos` (`name`, `uso_id`, `rubro_id`).
  Componente `resources/views/components/contratos/productos.blade.php`
  (seleccionar rubro → nombre + código de uso → tabla editar/borrar).
- Paquete **maatwebsite/excel** ya instalado.

Reglas de negocio confirmadas por el cliente:
- La empresa **solo compra** bienes o servicios (es el comprador/pagador que
  practica retenciones a la factura del proveedor). Nunca vende.
- "compra/servicio" = naturaleza del gasto (**bien** vs **servicio**), no
  venta vs compra.

---

## 1. Concepto general

- Las retenciones se calculan **por cada línea de factura** (no en el catálogo
  de producto).
- Cada línea genera **varias retenciones**; el porcentaje de cada una se
  resuelve con un **motor de reglas parametrizable** + un caso especial
  (Reteica servicio).
- **Tres** familias de retenciones según su disparador:

| Familia | Depende de | Ejemplos | Se asigna vía |
|---------|-----------|----------|---------------|
| **Tributarias (generales)** | Proveedor (régimen) + naturaleza de la línea | Retefuente, Reteiva, Reteica | Derivación A (ya existe) |
| **Parafiscales (por producto)** | El producto en sí | Fedepapa (papa), Asohofrucol (hortofrutícola) | Pivote producto ↔ retención |
| **Territoriales (por ubicación)** | Departamento donde se ejecuta/entrega la factura | Estampilla Magdalena | Tabla `estampilla_tarifas` |

Notas de negocio importantes:
- **Fedepapa** aplica porque el producto es **papa**. **Asohofrucol** porque el
  producto es **hortofrutícola**. Son parafiscales → dependen del PRODUCTO, no
  del régimen del proveedor.
- **es_agricola** es una característica intrínseca del producto; se usa
  **solo** para la tarifa de Retefuente en compra (agrícola 1% vs no agrícola 2%).
- **Reteica es municipal** (depende del municipio) y la **estampilla es
  departamental** (depende del departamento). Como todo municipio pertenece a un
  departamento, **basta el `municipio_id` de la línea**: Reteica usa el municipio
  y la estampilla usa `municipio.departamento`.
- **El municipio va por LÍNEA, no por factura.** Una misma factura puede tener
  ítems entregados/ejecutados en **lugares diferentes**, así que cada línea
  define su propio municipio de entrega/ejecución. La factura puede tener un
  municipio **por defecto** solo para autollenar líneas, pero el cálculo de
  Reteica y estampilla se hace con el municipio de **cada línea**.

---

## 2. Cambios en datos existentes

### 2.1 `retenciones` (agregar)
- `tipo` → `general` | `parafiscal` | `territorial`.
- (Ya tiene `aplica_base`, `aplica_iva`.)

### 2.2 `proveedores` (agregar)
- `es_declarante` (boolean) → decide tarifa de Retefuente.
- `codigo_actividad_economica` (CIIU del RUT) — informativo.
- `descripcion_actividad` (opcional) — informativo.

### 2.3 `productos` (agregar)
- `es_agricola` (boolean) → solo tarifa Retefuente en compra (1% vs 2%).
  Autollenable por rubro, editable.

---

## 3. Catálogos y tablas nuevas

### 3.1 `municipios`
- `id`, `codigo_dane` (opcional), `nombre`, `departamento`.
- Cargar solo los municipios donde se opera.

### 3.2 `producto_retencion` (pivote — parafiscales por producto)
- `producto_id`, `retencion_id`.
- Solo para retenciones `tipo = parafiscal`.
- Ej: producto "Papa" → Fedepapa; "Tomate" → Asohofrucol.

### 3.3 `retencion_tarifas` (motor de reglas parametrizable)
| campo | valores | significado |
|-------|---------|-------------|
| `retencion_id` | – | a qué retención aplica |
| `es_declarante` | true / false / **null** | null = no importa |
| `tipo_adquisicion` | bien / servicio / **null** | – |
| `es_agricola` | true / false / **null** | – |
| `porcentaje` | decimal | tarifa |

Regla del resolver: elige la fila **más específica** que haga match (más
condiciones concretas coincidentes gana).

### 3.4 `reteica_tarifas` (caso especial: Reteica servicio)
- `proveedor_id`, `municipio_id`, `porcentaje`, `codigo_actividad` (opcional).
- `unique(proveedor_id, municipio_id)`.
- La tarifa de Reteica servicio depende de **municipio × actividad económica del
  proveedor** (se saca manual del RUT). Se **captura una sola vez** por
  proveedor+municipio y se reutiliza. Si falta, el resolver marca PENDIENTE y
  permite capturarla en el momento.

### 3.5 `estampilla_tarifas` (retenciones territoriales / estampillas)
| campo | valores | significado |
|-------|---------|-------------|
| `retencion_id` | – | retención `tipo = territorial` |
| `departamento` | texto | departamento que dispara la estampilla |
| `tipo_adquisicion` | bien / servicio / **null** | null = aplica a bien y servicio |
| `porcentaje` | decimal | tarifa |

- La estampilla es un **impuesto departamental**: se cobra en la línea cuyo
  municipio de entrega/ejecución pertenece al departamento configurado. Como el
  municipio va por línea, dentro de una misma factura **unas líneas pueden tener
  estampilla y otras no**, según dónde se entregue cada ítem.
- Fila inicial: **Estampilla Magdalena → departamento "Magdalena", ambos
  (null), 2%**.
- Diseñada como tabla para soportar futuras estampillas de otros departamentos
  sin tocar código.

---

## 4. Factura y líneas

### 4.1 `facturas`
- `id`, `proveedor_id`, `contrato_id`, `numero`, `fecha`, `estado`, totales…
- `municipio_id` (nullable) → **solo un valor por defecto** para autollenar las
  líneas nuevas; NO se usa para calcular.

### 4.2 `factura_lineas`
- `id`, `factura_id`, `producto_id`
- `tipo_adquisicion` (**bien** | **servicio**)
- `municipio_id` (nullable — municipio donde se entrega/ejecuta ESA línea;
  requerido si la línea genera Reteica servicio o cae en departamento con
  estampilla). **El municipio va aquí**, porque una factura puede tener líneas
  entregadas en lugares distintos.
- `valor_base`, `valor_iva`, `cantidad`…

### 4.3 `factura_linea_retenciones` (detalle calculado, auditoría e histórico)
- `factura_linea_id`, `retencion_id`
- `base_calculo` (base | iva)
- `porcentaje_aplicado`, `valor_retenido`

> Se guarda el **% aplicado** (no solo la relación): si mañana cambia la tarifa,
> las facturas antiguas conservan lo que realmente se retuvo.

---

## 5. Datos iniciales (seeders)

### 5.1 Clasificación de retenciones
| retención | tipo | base |
|-----------|------|------|
| Retefuente | general | base |
| Reteica | general | base |
| Reteiva | general | iva |
| Fedepapa | parafiscal | base |
| Asohofrucol | parafiscal | base |
| Estampilla Magdalena | territorial | base |

### 5.2 `retencion_tarifas`
| retención | declarante | tipo | agrícola | % |
|-----------|-----------|------|----------|---|
| Retefuente | false | – | – | 3.5 |
| Retefuente | true | servicio | – | 4 |
| Retefuente | true | bien | true | 1 |
| Retefuente | true | bien | false | 2 |
| Reteiva | – | – | – | 15 |
| Reteica | – | bien | – | 0.5 |
| Fedepapa | – | – | – | 1 |
| Asohofrucol | – | – | – | 1 |

*(Reteica servicio NO va aquí → sale de `reteica_tarifas`.)*
*(Estampillas NO van aquí → salen de `estampilla_tarifas`.)*

### 5.3 `estampilla_tarifas`
| retención | departamento | tipo | % |
|-----------|-------------|------|---|
| Estampilla Magdalena | Magdalena | – (ambos) | 2 |

---

## 6. Servicio resolver `CalculadoraRetenciones`

```
calcular(linea):
    prov = linea.factura.proveedor

    # 1. Reunir retenciones aplicables (tres fuentes)
    generales    = prov.retencionesAplicables            # por régimen (Derivación A)
    parafiscales = linea.producto.retencionesParafiscales # pivote producto_retencion
    territoriales= retenciones territoriales cuyo estampilla_tarifas
                   coincida con linea.municipio.departamento   # municipio por LÍNEA
    aplicables   = generales + parafiscales + territoriales

    resultado = []
    para cada ret en aplicables:

        # 2. Resolver el porcentaje
        si ret.tipo == territorial:
            e = estampilla_tarifas(ret, linea.municipio.departamento,
                                   linea.tipo_adquisicion)   # dept. de la LÍNEA
            si no existe -> continuar   # no aplica en ese departamento
            pct = e.porcentaje
        sino si ret == Reteica y linea.tipo_adquisicion == servicio:
            t = reteica_tarifas(prov, linea.municipio_id)  # municipio por LÍNEA
            si no existe -> marcar PENDIENTE(ret); continuar
            pct = t.porcentaje
        sino:
            regla = retencion_tarifas.mejorMatch(
                        ret,
                        declarante: prov.es_declarante,
                        tipo:       linea.tipo_adquisicion,
                        agricola:   linea.producto.es_agricola)
            si no existe -> marcar PENDIENTE(ret); continuar
            pct = regla.porcentaje

        # 3. Base sobre la que se aplica
        base = ret.aplica_iva ? linea.valor_iva : linea.valor_base

        resultado[] = {
            retencion: ret, porcentaje: pct,
            base_calculo: ret.aplica_iva ? 'iva' : 'base',
            valor_retenido: base * pct / 100
        }

    return resultado
```

- Se ejecuta al **guardar/editar** cada línea y persiste en
  `factura_linea_retenciones`.
- Si alguna queda **PENDIENTE** (falta tarifa Reteica o falta regla), se avisa y
  se permite capturar la tarifa ahí mismo.

---

## 7. Interfaz de usuario

| Pantalla | Cambio |
|----------|--------|
| **Retenciones (CRUD)** | Campo `tipo` (general/parafiscal/territorial) |
| **Proveedor** | Check "Declarante" + actividad económica (CIIU) |
| **Producto** | Check "Es agrícola" + selección **manual** de retenciones parafiscales que dispara (checkboxes, **se permiten varias**; quien configura es responsable de lo que marca) |
| **Municipios** | CRUD nuevo (con `departamento`) |
| **Tarifas Reteica** | Gestión `reteica_tarifas` (proveedor + municipio + %) |
| **Tarifas estampillas** | Gestión `estampilla_tarifas` (departamento + % por estampilla) |
| **Tarifas generales** | (Opcional) CRUD de `retencion_tarifas` para editar % sin código |
| **Factura** | Cabecera con `municipio` por defecto (autollena líneas) + líneas; **cada línea** con `tipo_adquisicion` y su propio `municipio` de entrega/ejecución + **retenciones calculadas en vivo** (incluye estampilla si el departamento de esa línea aplica); captura de tarifa Reteica si falta |

---

## 8. Orden de implementación sugerido

1. **Fase A — Base de datos y catálogos:** campos nuevos en `retenciones`,
   `proveedores`, `productos`; tablas `municipios`, `producto_retencion`,
   `retencion_tarifas`, `reteica_tarifas`, `estampilla_tarifas`; seeders.
2. **Fase B — Ajustes UI de catálogos:** retención `tipo`, proveedor
   declarante/actividad, producto agrícola/parafiscales, CRUD municipios,
   gestión reteica_tarifas, gestión estampilla_tarifas.
3. **Fase C — Modelo de factura:** `facturas`, `factura_lineas`,
   `factura_linea_retenciones` + relaciones.
4. **Fase D — Resolver:** servicio `CalculadoraRetenciones` + persistencia del
   detalle.
5. **Fase E — Pantalla de factura:** captura de líneas + cálculo en vivo +
   manejo de pendientes.
6. **Fase F — Reportes/consultas:** totales retenidos por factura, proveedor,
   retención, periodo.

---

## 9. Ejemplos trabajados

**A — Compra de producto agrícola (papa), proveedor declarante.** Régimen aporta
Retefuente + Reteica; producto aporta Fedepapa. Base $1.000.000:
- Retefuente: declarante + bien + agrícola → 1% → **$10.000**
- Reteica: bien → 0.5% → **$5.000**
- Fedepapa: producto papa → 1% → **$10.000**
- **Total: $25.000**

**B — Servicio en Bogotá, proveedor Responsable de IVA, declarante.** Aplica
Retefuente + Reteica + Reteiva. Base $1.000.000, IVA $190.000:
- Retefuente: declarante + servicio → 4% sobre base → **$40.000**
- Reteiva: 15% sobre IVA → **$28.500**
- Reteica servicio: `reteica_tarifas(prov, Bogotá)` = 0.966% sobre base → **$9.660**
- **Total: $78.160**

**C — Compra no agrícola, proveedor NO declarante.** Base $2.000.000:
- Retefuente: no declarante → 3.5% → **$70.000**
- Reteica: bien → 0.5% → **$10.000**
- **Total: $80.000**

**D — Servicio de tomate (hortofrutícola), proveedor declarante, en Medellín.**
Base $500.000, IVA $0:
- Retefuente: declarante + servicio → 4% → **$20.000**
- Reteica servicio: `reteica_tarifas(prov, Medellín)` = 0.7% → **$3.500**
- Asohofrucol: producto hortofrutícola → 1% → **$5.000**
- **Total: $28.500**

**E — Factura con dos líneas de servicio en municipios distintos, proveedor
declarante.** El municipio va por línea:

*Línea 1 — servicio en Santa Marta (Magdalena), base $1.000.000:*
- Retefuente: declarante + servicio → 4% → **$40.000**
- Reteica servicio: `reteica_tarifas(prov, Santa Marta)` = 0.7% → **$7.000**
- Estampilla Magdalena: departamento = Magdalena → 2% → **$20.000**
- Subtotal línea 1: **$67.000**

*Línea 2 — servicio en Medellín (Antioquia), base $1.000.000:*
- Retefuente: declarante + servicio → 4% → **$40.000**
- Reteica servicio: `reteica_tarifas(prov, Medellín)` = 0.7% → **$7.000**
- Estampilla: Antioquia no está en `estampilla_tarifas` → **no aplica**
- Subtotal línea 2: **$47.000**

- **Total factura: $114.000**
- *(La estampilla aparece SOLO en la línea entregada en Magdalena.)*

---

## 10. Decisiones de diseño clave (para no reabrir discusiones)

1. El cálculo va en la **línea de factura**, nunca en el catálogo de producto.
2. Retenciones **generales** se derivan del proveedor (Derivación A ya existe);
   **parafiscales** se enganchan al producto (pivote `producto_retencion`).
3. Los **porcentajes son datos** (`retencion_tarifas` / `reteica_tarifas`), no
   código: cambiar normativa = editar filas, sin redeploy.
4. Reteica servicio se **captura manual una vez** por proveedor+municipio y se
   reutiliza (no se intenta calcular desde tablas ICA nacionales).
5. Se **persiste el % aplicado** en cada retención de cada línea (histórico
   inmutable).
6. `es_agricola` es del producto; declarante/actividad son del proveedor;
   bien/servicio y **el municipio de entrega/ejecución son de la LÍNEA**. Una
   factura puede tener líneas en municipios/departamentos distintos, por lo que
   Reteica y estampilla se calculan por línea. La factura solo guarda un
   municipio por defecto para autollenar líneas.
7. Las parafiscales se **asignan manualmente por producto** (no se deducen del
   rubro/uso). La UI permite marcar **varias** por producto mediante checkboxes;
   quien configura es responsable de lo que selecciona.
8. Existe un **tercer disparador: territorial (estampillas)**. Reteica es
   **municipal**, la estampilla es **departamental**; ambas se resuelven con el
   `municipio_id` de **cada línea** (la estampilla usa su departamento). Dentro
   de una misma factura, unas líneas pueden llevar estampilla/Reteica y otras no,
   según dónde se entregue cada ítem. Las tarifas de estampilla viven en
   `estampilla_tarifas` (parametrizable para más departamentos).
9. La estampilla se aplica **automáticamente según el municipio elegido en la
   línea** (por su departamento). El usuario NO marca la estampilla a mano; solo
   escoge el municipio de entrega/ejecución y el sistema decide. Esto evita error
   humano.

---

## 11. Preguntas abiertas / por confirmar con el equipo

> **RESUELTO:** No se manejan topes/cuantías mínimas en UVT. Como todo deriva del
> valor total del contrato (montos grandes que siempre superan cualquier piso),
> **la retención se practica siempre**. No se requieren tabla `uvt` ni campo
> `base_minima_uvt`.
- ¿Una línea puede ser bien y servicio a la vez? (se asume que no).
> **RESUELTO:** El municipio se define **por LÍNEA** (campo `municipio_id` en
> `factura_lineas`), porque una factura puede tener ítems entregados/ejecutados
> en municipios/departamentos distintos. La factura guarda un `municipio_id`
> opcional solo como valor por defecto para autollenar líneas nuevas.
> **RESUELTO:** Los valores retenidos se redondean a **2 decimales**.
> **RESUELTO:** Reteica compra usa una **tarifa única** (todas las compras se
> hacen en un mismo municipio), hoy **0.5%**. No depende del municipio, pero
> puede cambiar en el futuro: por eso vive como fila editable en
> `retencion_tarifas` (retención Reteica, `tipo_adquisicion = bien`), ajustable
> sin tocar código.

> **RESUELTO:** ¿Un producto puede disparar varias parafiscales? Sí. Se asignan
> manualmente por producto (checkboxes) y la UI permite seleccionar varias.
