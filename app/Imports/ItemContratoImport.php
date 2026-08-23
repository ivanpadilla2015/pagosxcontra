<?php

namespace App\Imports;

use App\Models\Itemcontrato;
use App\Models\Movirubro;
use App\Models\Producto;
use App\Models\Uso;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemContratoImport implements ToCollection, WithHeadingRow
{
    /** @var int */
    public $creados = 0;

    /** @var int */
    public $omitidos = 0;

    /** @var array */
    public $errores = [];

    /** @var int */
    private $fila = 1;

    /** @var int */
    private $contratoId;

    /** @var int */
    private $rubroId;

    public function __construct(int $contratoId, int $rubroId)
    {
        $this->contratoId = $contratoId;
        $this->rubroId = $rubroId;
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function getVal(Collection $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        return $default;
    }

    private function parseNumero(string $valor): float
    {
        $valor = trim($valor);
        if ($valor === '') return 0;

        $tienePunto = str_contains($valor, '.');
        $tieneComa = str_contains($valor, ',');

        if ($tienePunto && $tieneComa) {
            if (strrpos($valor, ',') > strrpos($valor, '.')) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($tieneComa) {
            $partes = explode(',', $valor);
            if (count($partes) === 2 && strlen($partes[1]) <= 2) {
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        }

        return (float) $valor;
    }

    public function collection(Collection $rows): void
    {
        $movirubroDefault = Movirubro::where('contrato_id', $this->contratoId)
            ->where('rubro_id', $this->rubroId)
            ->orderByDesc('saldo_rubro')
            ->first();

        if (!$movirubroDefault) {
            $this->errores[] = "No se encontró un movirubro disponible para este rubro en el contrato.";
            return;
        }

        foreach ($rows as $row) {
            $this->fila++;

            try {
                $nombreProducto = $this->getVal($row, ['nombre_producto', 'Nombre Producto']);
                $codigoUso = $this->getVal($row, ['codigo_uso', 'Codigo Uso']);
                $valorCostoStr = $this->getVal($row, ['valor_costo', 'Valor Costo']);
                $ivaStr = $this->getVal($row, ['iva', 'IVA', 'IVA %', 'iva_porcentaje']);
                $valorTotalStr = $this->getVal($row, ['valor_total', 'Valor Total (con IVA)', 'valor_total_con_iva']);

                if (empty($nombreProducto)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Nombre Producto' es obligatorio";
                    continue;
                }

                if (empty($codigoUso)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Codigo Uso' es obligatorio";
                    continue;
                }

                if ($valorCostoStr === '' && $valorTotalStr === '') {
                    $this->errores[] = "Fila {$this->fila}: Debe indicar 'Valor Costo' o 'Valor Total (con IVA)'";
                    continue;
                }

                $uso = Uso::where('codigo_uso', $codigoUso)->first();
                if (!$uso) {
                    $this->errores[] = "Fila {$this->fila}: No se encontró el uso '{$codigoUso}'";
                    continue;
                }

                $producto = Producto::where('name', $nombreProducto)
                    ->where('uso_id', $uso->id)
                    ->first();

                if (!$producto) {
                    $this->errores[] = "Fila {$this->fila}: No se encontró el producto '{$nombreProducto}' con uso '{$codigoUso}'";
                    continue;
                }

                if ($producto->rubro_id != $this->rubroId) {
                    $this->errores[] = "Fila {$this->fila}: El producto '{$nombreProducto}' no pertenece al rubro seleccionado";
                    continue;
                }

                $existe = Itemcontrato::where('contrato_id', $this->contratoId)
                    ->where('rubro_id', $this->rubroId)
                    ->where('producto_id', $producto->id)
                    ->exists();

                if ($existe) {
                    $this->omitidos++;
                    continue;
                }

                if (str_starts_with(trim($valorCostoStr), '=')) {
                    $this->errores[] = "Fila {$this->fila}: 'Valor Costo' no puede ser una fórmula de Excel. Pegue el valor numérico.";
                    continue;
                }
                if (str_starts_with(trim($ivaStr), '=')) {
                    $this->errores[] = "Fila {$this->fila}: 'IVA' no puede ser una fórmula de Excel. Pegue el valor numérico.";
                    continue;
                }
                if (str_starts_with(trim($valorTotalStr), '=')) {
                    $this->errores[] = "Fila {$this->fila}: 'Valor Total' no puede ser una fórmula de Excel. Pegue el valor numérico.";
                    continue;
                }

                $valorCosto = $this->parseNumero($valorCostoStr ?: '0');
                $valorIva = $this->parseNumero($ivaStr ?: '0');
                $valorTotal = $this->parseNumero($valorTotalStr ?: '0');

                if ($valorCosto <= 0) {
                    $this->errores[] = "Fila {$this->fila}: 'Valor Costo' debe ser mayor a 0";
                    continue;
                }

                if ($valorIva < 0) {
                    $this->errores[] = "Fila {$this->fila}: 'IVA' no puede ser negativo";
                    continue;
                }

                if ($valorTotal <= 0) {
                    $this->errores[] = "Fila {$this->fila}: 'Valor Total (con IVA)' debe ser mayor a 0";
                    continue;
                }

                $porcentajeIva = $valorCosto > 0 ? round($valorIva / $valorCosto * 100, 2) : 19;

                Itemcontrato::create([
                    'contrato_id' => $this->contratoId,
                    'producto_id' => $producto->id,
                    'rubro_id' => $this->rubroId,
                    'movirubro_id' => $movirubroDefault->id,
                    'valor_costo' => $valorCosto,
                    'iva' => $porcentajeIva,
                    'valor_iva' => $valorIva,
                    'valor_con_iva' => $valorTotal,
                    'unidad' => 'Und',
                ]);

                $this->creados++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$this->fila}: {$e->getMessage()}";
            }
        }
    }
}
