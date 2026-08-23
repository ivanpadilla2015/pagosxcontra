<?php

namespace App\Imports;

use App\Models\Obligacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ObligacionesImport implements ToCollection, WithHeadingRow
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

    public function __construct(int $contratoId)
    {
        $this->contratoId = $contratoId;
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

    private function findKeyContaining(Collection $row, array $needles): ?string
    {
        foreach ($row->keys() as $key) {
            $lower = strtolower($key);
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $key;
                }
            }
        }
        return null;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->fila++;

            try {
                if ($this->fila === 2) {
                    \Log::info('ObligacionesImport - Claves de fila:', array_keys($row->toArray()));
                }

                $numeral = $this->getVal($row, ['numeral', 'Numeral']);
                $obligacionDeta = $this->getVal($row, ['obligacion_deta', 'obligacion_detalle', 'obligacion (detalle)', 'Obligación (Detalle)']);
                $entregable = $this->getVal($row, ['entregable', 'Entregable']);

                if (empty($obligacionDeta)) {
                    $fallbackKey = $this->findKeyContaining($row, ['obligacion', 'detalle']);
                    if ($fallbackKey && trim((string) $row[$fallbackKey]) !== '') {
                        $obligacionDeta = trim((string) $row[$fallbackKey]);
                    }
                }

                if (empty($numeral)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Numeral' es obligatorio";
                    continue;
                }

                if (empty($obligacionDeta)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Obligación (Detalle)' es obligatorio";
                    continue;
                }

                if (empty($entregable)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Entregable' es obligatorio";
                    continue;
                }

                $existe = Obligacion::where('contrato_id', $this->contratoId)
                    ->where('numeral', $numeral)
                    ->where('obligacion_deta', $obligacionDeta)
                    ->exists();

                if ($existe) {
                    $this->omitidos++;
                    continue;
                }

                Obligacion::create([
                    'numeral' => $numeral,
                    'obligacion_deta' => $obligacionDeta,
                    'entregable' => $entregable,
                    'contrato_id' => $this->contratoId,
                ]);

                $this->creados++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$this->fila}: {$e->getMessage()}";
            }
        }
    }
}
