<?php

namespace App\Imports;

use App\Models\Uso;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UsosImport implements ToModel, WithBatchInserts, WithChunkReading
{
    /**
     * Mapea cada fila del Excel a un modelo Uso.
     * Columnas esperadas: 0 = codigo_uso, 1 = nombre_uso, 2 = rubro_id.
     *
     * @return Model|null
     */
    public function model(array $row)
    {
        // Salta filas vacías (sin código de uso o sin rubro asociado).
        if (empty($row[0]) || empty($row[2])) {
            return null;
        }

        return new Uso([
            'codigo_uso' => trim((string) $row[0]),
            'nombre_uso' => trim((string) ($row[1] ?? '')),
            'rubro_id' => (int) $row[2],
        ]);
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
