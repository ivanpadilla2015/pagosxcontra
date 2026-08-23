<?php

namespace App\Imports;

use App\Models\Riesgo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RiesgosImport implements ToCollection, WithHeadingRow
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
                    \Log::info('RiesgosImport - Claves de fila:', array_keys($row->toArray()));
                }

                $tipo = $this->getVal($row, ['tipo', 'Tipo']);
                $descripcion = $this->getVal($row, ['descripcion', 'descripción', 'Descripcion', 'Descripción']);
                $tratamiento = $this->getVal($row, ['tratamiento', 'Tratamiento']);
                $responsable = $this->getVal($row, ['responsable', 'Responsable']);
                $periodicidad = $this->getVal($row, ['periodicidad', 'Periodicidad']);

                if (empty($descripcion)) {
                    $fallbackKey = $this->findKeyContaining($row, ['descripcion', 'descripci']);
                    if ($fallbackKey && trim((string) $row[$fallbackKey]) !== '') {
                        $descripcion = trim((string) $row[$fallbackKey]);
                    }
                }

                if (empty($tratamiento)) {
                    $fallbackKey = $this->findKeyContaining($row, ['tratamiento']);
                    if ($fallbackKey && trim((string) $row[$fallbackKey]) !== '') {
                        $tratamiento = trim((string) $row[$fallbackKey]);
                    }
                }

                if (empty($responsable)) {
                    $fallbackKey = $this->findKeyContaining($row, ['responsable']);
                    if ($fallbackKey && trim((string) $row[$fallbackKey]) !== '') {
                        $responsable = trim((string) $row[$fallbackKey]);
                    }
                }

                if (empty($descripcion)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Descripción' es obligatorio";
                    continue;
                }

                if (empty($tratamiento)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Tratamiento' es obligatorio";
                    continue;
                }

                if (empty($responsable)) {
                    $this->errores[] = "Fila {$this->fila}: El campo 'Responsable' es obligatorio";
                    continue;
                }

                $existe = Riesgo::where('contrato_id', $this->contratoId)
                    ->where('descripcion', $descripcion)
                    ->where('tratamiento', $tratamiento)
                    ->exists();

                if ($existe) {
                    $this->omitidos++;
                    continue;
                }

                Riesgo::create([
                    'tipo' => $tipo ?: null,
                    'descripcion' => $descripcion,
                    'tratamiento' => $tratamiento,
                    'responsable' => $responsable,
                    'periodicidad' => $periodicidad ?: null,
                    'contrato_id' => $this->contratoId,
                ]);

                $this->creados++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$this->fila}: {$e->getMessage()}";
            }
        }
    }
}
