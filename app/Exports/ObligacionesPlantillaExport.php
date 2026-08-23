<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObligacionesPlantillaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Numeral',
            'Obligación (Detalle)',
            'Entregable',
        ];
    }

    public function collection()
    {
        return collect([
            ['Cláusula 1.1', 'El contratista se compromete a ejecutar el objeto del contrato en los términos y condiciones establecidos.', 'Plan de trabajo aprobado'],
            ['Cláusula 2.3', 'El contratista entregará informes de avance mensuales.', 'Informe mensual de avance'],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
