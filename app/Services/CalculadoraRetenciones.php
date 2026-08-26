<?php

namespace App\Services;

use App\Models\FacturaLinea;
use App\Models\Retencion;
use App\Models\RetencionTarifa;
use App\Models\EstampillaTarifa;
use App\Models\ReteicaTarifa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CalculadoraRetenciones
{
    /**
     * Calcula las retenciones aplicables para una línea de factura.
     *
     * @param FacturaLinea $linea
     * @return array<int, array{retencion: Retencion, porcentaje: float, base_calculo: string, valor_retenido: float}>
     */
    public function calcular(FacturaLinea $linea): array
    {
        $producto = $linea->producto;
        if (!$producto) {
            $producto = \App\Models\Producto::find($linea->producto_id);
        }

        if ($linea->factura_id && $linea->factura) {
            $proveedor = $linea->factura->proveedor;
            if (!$proveedor) {
                $proveedor = \App\Models\Proveedor::find($linea->factura->proveedor_id);
            }
            $proveedor->load('regimenTributario.retenciones');
        } else {
            // Línea temporal sin factura: resolver proveedor desde el itemcontrato
            $item = $linea->itemcontrato_id ? \App\Models\Itemcontrato::with('contrato.proveedor.regimenTributario.retenciones')->find($linea->itemcontrato_id) : null;
            $proveedor = $item?->contrato?->proveedor;
        }

        if (!$proveedor) {
            return ['calculadas' => [], 'pendientes' => []];
        }

        $linea->setRelation('producto', $producto);

        // 1. Reunir retenciones aplicables (tres fuentes)
        $generales = $proveedor->retencionesAplicables->filter(fn ($r) => $r->tipo === 'general');
        $parafiscales = $producto ? $producto->retencionesParafiscales : collect();
        $territoriales = $this->obtenerTerritoriales($linea);

        $aplicables = $generales->concat($parafiscales)->concat($territoriales);

        $resultado = [];
        $pendientes = [];

        foreach ($aplicables as $retencion) {
            // 2. Resolver porcentaje
            if ($retencion->tipo === 'territorial') {
                $tarifa = $this->resolverTerritorial($retencion, $linea);
                if (!$tarifa) {
                    continue;
                }
                $pct = $tarifa->porcentaje;
            } elseif ($retencion->name === 'Reteica') {
                if ($linea->tipo_adquisicion === 'servicio') {
                    $tarifa = $this->resolverReteicaServicio($proveedor, $linea);
                    if (!$tarifa) {
                        $pendientes[] = $retencion;
                        continue;
                    }
                    $pct = $tarifa->porcentaje;
                } else {
                    $tarifa = $this->resolverReteicaBien($proveedor, $linea);
                    if (!$tarifa) {
                        $pendientes[] = $retencion;
                        continue;
                    }
                    $pct = $tarifa->porcentaje;
                }
            } else {
                $regla = $this->resolverTarifaGeneral($retencion, $proveedor, $linea);
                if (!$regla) {
                    $pendientes[] = $retencion;
                    continue;
                }
                $pct = $regla->porcentaje;
            }

            // 3. Base de cálculo
            $base = $retencion->aplica_iva ? $linea->valor_iva : $linea->valor_base;
            $baseCalculo = $retencion->aplica_iva ? 'iva' : 'base';

            $divisor = $retencion->divisor ?? 100;

            $resultado[] = [
                'retencion' => $retencion,
                'porcentaje' => $pct,
                'base_calculo' => $baseCalculo,
                'valor_retenido' => round($base * $pct / $divisor, 2),
            ];
        }

        return [
            'calculadas' => $resultado,
            'pendientes' => $pendientes,
        ];
    }

    /**
     * Obtiene las retenciones territoriales aplicables.
     * Solo se aplica si la línea tiene estampilla_retencion_id seleccionado.
     */
    private function obtenerTerritoriales(FacturaLinea $linea): Collection
    {
        if (empty($linea->estampilla_retencion_id)) {
            return collect();
        }

        return Retencion::where('tipo', 'territorial')
            ->where('id', $linea->estampilla_retencion_id)
            ->whereHas('estampillaTarifas', function ($q) {
                $q->whereNotNull('departamento');
            })
            ->get();
    }

    /**
     * Resuelve la tarifa territorial (estampilla).
     */
    private function resolverTerritorial(Retencion $retencion, FacturaLinea $linea): ?EstampillaTarifa
    {
        return EstampillaTarifa::where('retencion_id', $retencion->id)
            ->whereNotNull('departamento')
            ->first();
    }

    /**
     * Resuelve la tarifa de Reteica servicio (proveedor + municipio de la línea).
     */
    private function resolverReteicaServicio($proveedor, FacturaLinea $linea): ?ReteicaTarifa
    {
        if (!$linea->municipio_id) {
            return null;
        }

        return ReteicaTarifa::where('proveedor_id', $proveedor->id)
            ->where('municipio_id', $linea->municipio_id)
            ->first();
    }

    /**
     * Resuelve la tarifa de Reteica bien.
     * Prioridad: tarifa específica del proveedor > tarifa genérica (sin proveedor).
     */
    private function resolverReteicaBien($proveedor, FacturaLinea $linea): ?ReteicaTarifa
    {
        $municipioId = $linea->municipio_id;

        if (!$municipioId) {
            $user = Auth::user();
            $municipioId = $user?->regional?->municipio_id;
        }

        if (!$municipioId) {
            return null;
        }

        // 1. Buscar tarifa específica del proveedor
        if ($proveedor) {
            $tarifa = ReteicaTarifa::where('proveedor_id', $proveedor->id)
                ->where('municipio_id', $municipioId)
                ->where('tipo_adquisicion', 'bien')
                ->first();

            if ($tarifa) {
                return $tarifa;
            }
        }

        // 2. Fallback: tarifa genérica (sin proveedor)
        return ReteicaTarifa::whereNull('proveedor_id')
            ->where('municipio_id', $municipioId)
            ->where('tipo_adquisicion', 'bien')
            ->first();
    }

    /**
     * Resuelve la tarifa general (Retefuente, Reteiva, Reteica bien).
     */
    private function resolverTarifaGeneral(Retencion $retencion, $proveedor, FacturaLinea $linea): ?RetencionTarifa
    {
        $query = RetencionTarifa::where('retencion_id', $retencion->id);

        // Filtrar por declarante
        $query->where(function ($q) use ($proveedor) {
            $q->where('es_declarante', $proveedor->es_declarante)
              ->orWhereNull('es_declarante');
        });

        // Filtrar por tipo_adquisicion
        $query->where(function ($q) use ($linea) {
            $q->where('tipo_adquisicion', $linea->tipo_adquisicion)
              ->orWhereNull('tipo_adquisicion');
        });

        // Filtrar por es_agricola
        $query->where(function ($q) use ($linea) {
            $q->where('es_agricola', $linea->producto->es_agricola)
              ->orWhereNull('es_agricola');
        });

        // Obtener todas las que coinciden y elegir la más específica
        $candidatos = $query->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        // Elegir la más específica (más condiciones concretas)
        return $candidatos->sortBy(function ($t) {
            $specificidad = 0;
            if (!is_null($t->es_declarante)) $specificidad++;
            if (!is_null($t->tipo_adquisicion)) $specificidad++;
            if (!is_null($t->es_agricola)) $specificidad++;
            return -$specificidad;
        })->first();
    }

    /**
     * Calcula y persiste las retenciones para una línea de factura.
     */
    public function calcularYPersistir(FacturaLinea $linea): array
    {
        $resultado = $this->calcular($linea);

        // Eliminar retenciones anteriores de esta línea
        $linea->retenciones()->delete();

        // Persistir las nuevas
        foreach ($resultado['calculadas'] as $calculo) {
            $linea->retenciones()->create([
                'retencion_id' => $calculo['retencion']->id,
                'base_calculo' => $calculo['base_calculo'],
                'porcentaje_aplicado' => $calculo['porcentaje'],
                'valor_retenido' => $calculo['valor_retenido'],
            ]);
        }

        return $resultado;
    }
}
