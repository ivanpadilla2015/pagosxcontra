<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosPlantillaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * Encabezados de la plantilla.
     */
    public function headings(): array
    {
        return [
            'Código Uso',
            'Nombre Producto',
            'Tipo (bien/servicio)',
            'Es Agrícola (sí/no)',
            'Municipio (opcional)',
        ];
    }

    /**
     * Filas de ejemplo (vacías, solo para mostrar la estructura).
     */
    public function collection()
    {
        return collect([
            ['USO-001', 'Ejemplo Producto Bien', 'bien', 'no', ''],
            ['USO-002', 'Ejemplo Servicio', 'servicio', 'sí', 'Barranquilla'],
        ]);
    }

    /**
     * Estilos: encabezados en negrita.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
