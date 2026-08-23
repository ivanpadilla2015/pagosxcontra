# Plan Consolidado — Módulo de Pagos

## 1. Estructura de tablas

### `pagos`
| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | bigint PK | No | |
| numero | varchar(50) | No | `{cansecu_pagos}-{año}` |
| fecha | date | No | Fecha del pago |
| contrato_id | FK → contratos | No | |
| informe_id | FK → informes | Sí | Se asigna al asociar a informe |
| tramite_pago_id | FK → tramite_pagos | Sí | Se asigna al asociar a trámite |
| valor_total | decimal(14,2) | No | Suma de detalles |
| estado | varchar(20) | No | `abierto` / `cerrado` |
| fecha_cierre | date | Sí | Fecha de cierre del pago |
| user_id | FK → users | No |
| timestamps | | | |

### `detalle_pagos`
| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | bigint PK | No | |
| pago_id | FK → pagos | No | |
| factura_id | FK → facturas | No | |
| valor_pagado | decimal(14,2) | No | Valor pagado de esa factura |
| timestamps | | | |

### `informes`
| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | bigint PK | No | |
| numero | varchar(50) | No | `{cansecu_infor}-{año}` |
| fecha | date | No | |
| contrato_id | FK → contratos | No | |
| tramite_pago_id | FK → tramite_pagos | Sí | |
| estado | varchar(20) | No | `abierto` / `cerrado` |
| user_id | FK → users | No |
| timestamps | | | |

### `tramite_pagos`
| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | bigint PK | No | |
| numero | varchar(50) | No | `{cansecu_tramite}-{año}` |
| fecha | date | No | |
| contrato_id | FK → contratos | No | |
| estado | varchar(20) | No | `abierto` / `cerrado` |
| user_id | FK → users | No |
| timestamps | | | |

---

## 2. Corrección en Contrato model
- Agregar `cansecu_pagos` al `$fillable`

---

## 3. Estados y reglas de negocio

| Entidad | Estados | Regla |
|---------|---------|-------|
| Pago | `abierto` → `cerrado` | Solo modificar en estado `abierto` |
| Informe | `abierto` → `cerrado` | No crear informe nuevo si hay uno `abierto` |
| Trámite | `abierto` → `cerrado` | No crear trámite nuevo si hay uno `abierto` |
| Factura | `emitida` → `pagada` | Cambia al cerrar el pago |

---

## 4. Flujo de actualización de saldo

### Cadena existente
```
FacturaLinea → Itemcontrato → Movirubro → saldo_rubro
```

### Cuándo se toca el saldo

| Acción | Saldo |
|--------|-------|
| Crear factura (borrador) | Sin cambio |
| Emitir factura (emitida) | Sin cambio |
| Crear pago (abierto) | Sin cambio |
| **Cerrar pago** | **Descuenta saldo** |
| Anular pago cerrado | Restaura saldo |
| Anular factura pagada | Revisar manualmente |

### Al cerrar pago
```
Por cada Factura del Pago
  └── Por cada Línea de la Factura (factura_lineas)
        └── Itemcontrato → Movirubro
              └── saldo_rubro -= valor_base × cantidad
```

### Al anular pago
```
Por cada Factura del Pago
  └── Por cada Línea de la Factura
        └── Itemcontrato → Movirubro
              └── saldo_rubro += valor_base × cantidad
  └── Facturas: pagada → emitida
```

---

## 5. Valor a descontar

Por cada línea de factura: `valor_base × cantidad`

---

## 6. Visualización al crear pago

Facturas agrupadas por código de uso:
```
Uso A-01-01-01-001-001 — Edificios
  ├── Factura 001-2026 → $500.000
  └── Factura 002-2026 → $300.000
  Subtotal: $800.000

Uso A-01-01-01-001-002 — Mobiliario
  └── Factura 003-2026 → $200.000
  Subtotal: $200.000

Total pago: $1.000.000
```

---

## 7. Validaciones

1. Saldo suficiente: `movirubro.saldo_rubro >= valor_a_descontar`
2. Solo facturas con estado `emitida`
3. Solo modificar pagos en estado `abierto`
4. No crear informe nuevo si hay uno `abierto`
5. No crear trámite nuevo si hay uno `abierto`

---

## 8. Relaciones en modelos

| Modelo | Relaciones |
|--------|-----------|
| Contrato | `pagos()`, `informes()`, `tramitePagos()` |
| Pago | `contrato()`, `factura()`, `informe()`, `tramitePago()`, `detalles()` |
| DetallePago | `pago()`, `factura()` |
| Informe | `contrato()`, `tramitePago()`, `pagos()` |
| TramitePago | `contrato()`, `informes()`, `pagos()` |
| Factura | `pagos()` |

---

## 9. Componentes a crear

| Componente | Ruta |
|------------|------|
| pagos-lista | `/contratos/pagos` |
| pago-crear | `/contratos/pagos/crear` |
| pago-editar | `/contratos/pagos/{id}/editar` |
| informes-lista | `/contratos/informes` |
| informe-crear | `/contratos/informes/crear` |
| tramites-lista | `/contratos/tramites` |
| tramite-crear | `/contratos/tramites/crear` |

---

## 10. Sidebar

```
Contratos
├── ...
├── Pagos
├── Informes
└── Trámites
```

---

**Estado:** Pendiente de aprobación del equipo.
