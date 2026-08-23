<?php

namespace App\Imports;

use App\Models\Rubro;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class RubrosImport implements ToModel, WithBatchInserts, WithChunkReading
{
    /**
     * Mapea cada fila del Excel a un modelo Rubro.
     * Columnas esperadas: 0 = codigo_rubro, 1 = nombre_rubro.
     *
     * @return Model|null
     */
    public function model(array $row)
    {
        // Salta filas vacías (sin código de rubro).
        if (empty($row[0])) {
            return null;
        }

        return new Rubro([
            'codigo_rubro' => trim((string) $row[0]),
            'nombre_rubro' => trim((string) ($row[1] ?? '')),
        ]);
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
