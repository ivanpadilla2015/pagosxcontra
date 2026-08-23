<?php

namespace App\Imports;

use App\Models\Producto;
use App\Models\Uso;
use App\Models\Municipio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductosImport implements ToCollection, WithHeadingRow, WithValidation
{
    /** @var int */
    public $creados = 0;

    /** @var int */
    public $omitidos = 0;

    /** @var array */
    public $errores = [];

    /** @var int */
    private $fila = 1;

    /**
     * Encabezados esperados en el Excel:
     * - codigo_uso (requerido)
     * - nombre_producto (requerido)
     * - tipo (opcional: bien/servicio, default bien)
     * - es_agricola (opcional: sí/no/1/0, default no)
     */
    public function rules(): array
    {
        return [
            'codigo_uso' => ['required', 'string', 'exists:usos,codigo_uso'],
            'nombre_producto' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:bien,servicio'],
            'municipio' => ['nullable', 'string'],
            'es_agricola' => ['nullable', 'string'],
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->fila++;

            try {
                $uso = Uso::where('codigo_uso', trim($row['codigo_uso']))->first();

                if (! $uso) {
                    $this->errores[] = "Fila {$this->fila}: No se encontró el uso '{$row['codigo_uso']}'";
                    continue;
                }

                $nombre = trim($row['nombre_producto']);
                $tipoRaw = trim($row['tipo_bienservicio'] ?? $row['tipo'] ?? 'bien');
                $tipo = strtolower($tipoRaw) === 'servicio' ? 'servicio' : 'bien';
                $esAgricolaRaw = trim($row['es_agricola_sino'] ?? $row['es_agricola'] ?? 'no');
                $esAgricola = in_array(strtolower($esAgricolaRaw), ['sí', 'si', '1', 'true', 'yes'], true);

                // Buscar municipio por nombre (opcional)
                $municipioId = null;
                $municipioNombre = trim($row['municipio'] ?? '');
                if ($municipioNombre !== '') {
                    $municipio = Municipio::where('nombre', $municipioNombre)
                        ->where('regional_id', Auth::user()->regional_id)
                        ->first();
                    if ($municipio) {
                        $municipioId = $municipio->id;
                    }
                }

                // Verificar si ya existe un producto con el mismo nombre y uso
                $existe = Producto::where('name', $nombre)
                    ->where('uso_id', $uso->id)
                    ->exists();

                if ($existe) {
                    $this->omitidos++;
                    continue;
                }

                Producto::create([
                    'name' => $nombre,
                    'uso_id' => $uso->id,
                    'rubro_id' => $uso->rubro_id,
                    'tipo' => $tipo,
                    'es_agricola' => $esAgricola,
                    'regional_id' => Auth::user()->regional_id,
                    'municipio_id' => $municipioId,
                ]);

                $this->creados++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$this->fila}: {$e->getMessage()}";
            }
        }
    }
}
