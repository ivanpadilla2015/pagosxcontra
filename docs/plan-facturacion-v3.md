# Plan: Motor de Facturación con Retenciones

**Estado:** PROPUESTA / PENDIENTE DE APROBACIÓN
**Fecha:** 2026-07-16
**Stack:** Laravel + Livewire 4 + Volt

---

## 0. Contexto actual

### Ya existe y funciona:
- **Contrato** (`contratos`): numcontrato, fechas, proveedor, tipo, poliza
- **Itemcontrato** (`itemcontratos`): pivote contrato ↔ producto con payload:
  `valor_costo`, `iva` (%), `valor_iva`, `valor_con_iva`, `unidad`, `movirubro_id`, `rubro_id`
- **Producto** (`productos`): name, rubro_id, uso_id, regional_id
- **Proveedor** (`proveedors`): con `getRetencionesAplicablesAttribute()` (Derivación A)
- **Retenciones** (`retenciones`): name, aplica_base, aplica_iva
- **Régimen ↔ Retenciones**: pivote `regimen_retencion` funcional
- **Movirubros** (`movirubros`): valor_rubro, saldo_rubro, registro_id, rubro_id, contrato_id
- **Registros** (`registros`): con tiporegistro_id (1=Primer Registro, 2=Adición, 3=Reducción)

### No existe nada de facturación:
Ni tablas, ni modelos, ni componentes, ni el servicio calculadora.

---

## 1. Cadena de datos

```
Contrato (1) ──< (N) Movirubro          (presupuesto: valor y saldo)
     |                                         |
     └──< (N) Itemcontrato ──> (1) Producto   (productos asignados con valor)
                |
                └──> Proveedor
```

**Saldo del contrato:**
- Valor total = SUM(movirubros.valor_rubro) del contrato
- Saldo disponible = SUM(movirubros.saldo_rubro) del contrato
- Los tipos de registro: 1=Primer Registro, 2=Adición, 3=Reducción

**Itemcontrato (solo producto + valor unitario, SIN saldo):**
- valor_costo = valor unitario sin IVA
- iva = porcentaje de IVA (default 19%)
- valor_iva = valor del IVA unitario
- valor_con_iva = valor unitario con IVA

---

## 2. Concepto general

- Las retenciones se calculan **por cada línea de factura**
- Tres familias según disparador:

| Familia | Depende de | Ejemplos | Asignación |
|---------|-----------|----------|------------|
| **Tributarias (generales)** | Proveedor (régimen) + naturaleza línea | Retefuente, Reteiva, Reteica | Derivación A (ya existe) |
| **Parafiscales (por producto)** | El producto en sí | Fedepapa, Asohofrucol | Pivote `producto_retencion` |
| **Territoriales (por ubicación)** | Departamento de entrega de la línea | Estampilla Magdalena | Tabla `estampilla_tarifas` |

**Reglas clave:**
- El **municipio va por LÍNEA** (una factura puede tener ítems en lugares distintos)
- **Reteica** es municipal, **estampilla** es departamental
- Las parafiscales se **asignan manualmente por producto** (checkboxes)
- Se persiste el **% aplicado** en cada retención (histórico inmutable)

---

## 3. Flujo de facturación

```
1. Usuario busca contrato por número
2. Se muestra el contrato con su saldo disponible:
   - saldo = SUM(movirubros.saldo_rubro) del contrato
3. Se muestran TODOS los itemcontratos del contrato con checkboxes
4. Usuario SELECCIONA cuáles incluir en la factura
5. Sistema valida que la suma de los ítems seleccionados NO exceda el saldo
6. Usuario ingresa: número de factura, fecha
7. Solo los itemcontratos seleccionados se copian como líneas:
   - producto (del itemcontrato)
   - valor unitario sin IVA = valor_costo (NO editable)
   - IVA % = iva (NO editable)
   - unidad (del itemcontrato)
   - cantidad = 1 (por defecto, el usuario indica la real)
8. Por cada línea: usuario ingresa:
   - cantidad (real a facturar)
   - tipo_adquisicion (bien / servicio)
   - municipio_id (opcional, municipio de entrega/ejecución)
9. El sistema calcula automáticamente:
   - valor_base = valor_costo × cantidad
   - valor_iva = valor_iva × cantidad
   - retenciones (CalculadoraRetenciones resuelve en vivo)
10. Totales de la factura se calculan sumando las líneas:
    - subtotal = suma(valor_base de todas las líneas)
    - total_iva = suma(valor_iva de todas las líneas)
    - total_retenciones = suma(valor_retenido de todas las retenciones)
    - total = subtotal + total_iva - total_retenciones
11. Se persiste en factura_linea_retenciones con % aplicado
```

**Reglas de facturación:**
- **Múltiples facturas por contrato** (el contrato tiene un valor grande que se va descontando)
- **Saldo del contrato:** SUM(movirubros.saldo_rubro) — se actualiza al facturar
- **Validación obligatoria:** la factura no puede exceder el saldo disponible
- **Los valores unitarios NO son editables** en la factura, vienen del itemcontrato
- **Número de factura:** formato `{numero}-{año}`, único por proveedor
- **Totales se calculan automáticamente** sumando las líneas

---

## 4. Cambios en datos existentes

### 4.1 `retenciones` — agregar
- `tipo` → `general` | `parafiscal` | `territorial`

### 4.2 `proveedors` — agregar
- `es_declarante` (boolean) → tarifa de Retefuente
- `codigo_actividad_economica` (CIIU) — informativo
- `descripcion_actividad` (opcional) — informativo

### 4.3 `productos` — agregar
- `es_agricola` (boolean) → tarifa Retefuente 1% vs 2%

---

## 5. Tablas nuevas

### 5.1 `municipios`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| codigo_dane | string, nullable | |
| nombre | string | |
| departamento | string | |

### 5.2 `producto_retencion` (parafiscales por producto)
| Campo | Tipo |
|-------|------|
| producto_id | FK → productos |
| retencion_id | FK → retenciones |

### 5.3 `retencion_tarifas` (motor de reglas)
| Campo | Tipo | Notas |
|-------|------|-------|
| retencion_id | FK | |
| es_declarante | boolean, nullable | null = no importa |
| tipo_adquisicion | enum, nullable | bien / servicio |
| es_agricola | boolean, nullable | |
| porcentaje | decimal | |

### 5.4 `reteica_tarifas` (caso especial servicio)
| Campo | Tipo |
|-------|------|
| proveedor_id | FK |
| municipio_id | FK |
| porcentaje | decimal |
| codigo_actividad | string, nullable |

Unique: (proveedor_id, municipio_id)

### 5.5 `estampilla_tarifas` (territoriales)
| Campo | Tipo | Notas |
|-------|------|-------|
| retencion_id | FK | tipo = territorial |
| departamento | string | |
| tipo_adquisicion | enum, nullable | bien / servicio / ambos |
| porcentaje | decimal | |

### 5.6 `facturas`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| proveedor_id | FK | |
| contrato_id | FK | múltiples facturas por contrato |
| numero | string | formato `{numero}-{año}`, único por proveedor |
| fecha | date | fecha de la factura |
| estado | string | borrador / emitida / anulada |
| municipio_id | FK, nullable | solo para autollenar líneas |
| subtotal | decimal(14,2) | calculado |
| total_iva | decimal(14,2) | calculado |
| total_retenciones | decimal(14,2) | calculado |
| total | decimal(14,2) | calculado |
| timestamps | | |

Unique constraint: `(proveedor_id, numero)`

**Estados:**
- `borrador`: se puede editar
- `emitida`: no se puede editar (solo consultar)
- `anulada`: no se puede editar. Retenciones se conservan para historial

### 5.7 `factura_lineas`
| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| factura_id | FK | |
| itemcontrato_id | FK, nullable | trazabilidad al ítem del contrato |
| producto_id | FK | |
| tipo_adquisicion | enum | bien / servicio |
| municipio_id | FK, nullable | municipio de entrega/ejecución |
| valor_base | decimal(14,2) | base gravable (= valor_costo × cantidad) |
| valor_iva | decimal(14,2) | IVA calculado (= valor_iva × cantidad) |
| cantidad | decimal(10,2), default 1 | cantidad real a facturar |
| timestamps | | |

### 5.8 `factura_linea_retenciones` (detalle calculado)
| Campo | Tipo |
|-------|------|
| factura_linea_id | FK |
| retencion_id | FK |
| base_calculo | enum (base / iva) |
| porcentaje_aplicado | decimal(6,2) |
| valor_retenido | decimal(14,2) |

---

## 6. Datos iniciales (seeders)

### Retenciones
| Nombre | Tipo | Base |
|--------|------|------|
| Retefuente | general | base |
| Reteica | general | base |
| Reteiva | general | iva |
| Fedepapa | parafiscal | base |
| Asohofrucol | parafiscal | base |
| Estampilla Magdalena | territorial | base |

### retencion_tarifas
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

### estampilla_tarifas
| Retención | Departamento | Tipo | % |
|-----------|-------------|------|---|
| Estampilla Magdalena | Magdalena | ambos | 2 |

---

## 7. Servicio CalculadoraRetenciones

```
calcular(linea):
    prov = linea.factura.proveedor

    # 1. Reunir retenciones aplicables (tres fuentes)
    generales     = prov.retencionesAplicables
    parafiscales  = linea.producto.retencionesParafiscales
    territoriales = retenciones territoriales cuyo
                    estampilla_tarifas coincida con
                    linea.municipio.departamento
    aplicables = generales + parafiscales + territoriales

    resultado = []
    para cada ret en aplicables:
        # 2. Resolver porcentaje
        si ret.tipo == territorial:
            e = estampilla_tarifas(ret, depto, tipo)
            si no existe → continuar
            pct = e.porcentaje
        sino si ret == Reteica && tipo == servicio:
            t = reteica_tarifas(prov, municipio_id)
            si no existe → marcar PENDIENTE; continuar
            pct = t.porcentaje
        sino:
            regla = retencion_tarifas.mejorMatch(ret, ...)
            si no existe → marcar PENDIENTE; continuar
            pct = regla.porcentaje

        # 3. Base de cálculo
        base = ret.aplica_iva ? linea.valor_iva : linea.valor_base

        resultado[] = {
            retencion, porcentaje, base_calculo,
            valor_retenido: base * pct / 100
        }

    return resultado
```

---

## 8. Orden de implementación

| Fase | Descripción | Archivos |
|------|-------------|----------|
| **A** | DB + Catálogos | 12 migraciones + seeders |
| **B** | UI Catálogos | 6 ajustes/nuevos CRUD |
| **C** | Modelos Factura | 8 modelos + relaciones |
| **D** | Servicio | `app/Services/CalculadoraRetenciones.php` |
| **E** | Pantalla Factura | 1 componente Livewire + ruta + sidebar |
| **F** | Reportes | Totales por factura/proveedor/retención/período |

---

## 9. Ejemplos trabajados

**A — Compra papa, proveedor declarante.** Base $1.000.000:
- Retefuente: 1% → $10.000 | Reteica: 0.5% → $5.000 | Fedepapa: 1% → $10.000
- **Total: $25.000**

**B — Servicio en Bogotá, declarante.** Base $1.000.000, IVA $190.000:
- Retefuente: 4% → $40.000 | Reteiva: 15% → $28.500 | Reteica: 0.966% → $9.660
- **Total: $78.160**

**C — Compra no agrícola, no declarante.** Base $2.000.000:
- Retefuente: 3.5% → $70.000 | Reteica: 0.5% → $10.000
- **Total: $80.000**

**D — Servicio tomate (hortofrutícola), declarante, Medellín.** Base $500.000:
- Retefuente: 4% → $20.000 | Reteica: 0.7% → $3.500 | Asohofrucol: 1% → $5.000
- **Total: $28.500**

**E — Factura con 2 líneas en municipios distintos:**
- Línea 1 (Santa Marta, Magdalena): $67.000 retenciones
- Línea 2 (Medellín, Antioquia): $47.000 retenciones
- **Total: $114.000** (estampilla SOLO en línea de Magdalena)

---

## 10. Decisiones de diseño

1. El cálculo va en la **línea de factura**, no en el catálogo
2. Generales del proveedor (Derivación A), parafiscales del producto, territoriales del municipio
3. Las tarifas son **datos editables**, no código
4. Reteica servicio se captura una vez por proveedor+municipio
5. Se persiste el % aplicado (histórico inmutable)
6. El municipio va por **línea** (no por factura)
7. Parafiscales se asignan manualmente por producto (checkboxes)
8. La factura se **crea seleccionando itemcontratos** del contrato
9. `cantidad` vive en `factura_lineas`, NO en `itemcontratos`
10. **Múltiples facturas por contrato**
11. **Saldo del contrato** se calcula desde `movirubros.saldo_rubro`
12. Los **valores unitarios NO son editables** en la factura, vienen del itemcontrato
13. **Número de factura:** formato `{numero}-{año}`, único por proveedor
14. **Totales se calculan automáticamente** sumando las líneas
15. **Edición permitida** en estado "borrador"

---

## 11. Preguntas abiertas

- ¿Una línea puede ser bien y servicio a la vez? (se asume que no)
- ¿Los valores retenidos se redondean a 2 decimales? (se asume que sí)
- ¿Se permite anular facturas con retenciones pendientes de pago? (se asume que sí)
- ¿Se muestra historial de facturas por contrato? (se asume que sí)
