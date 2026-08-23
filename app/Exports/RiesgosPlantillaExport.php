<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RiesgosPlantillaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Tipo',
            'Descripción',
            'Tratamiento',
            'Responsable',
            'Periodicidad',
        ];
    }

    public function collection()
    {
        return collect([
            ['Estratégico', 'Retraso en la entrega de materiales por parte del proveedor.', 'Seguimiento semanal al estado de entrega y alerta temprana.', 'Carlos Méndez', 'Mensual'],
            ['Operativo', 'Falla en los equipos suministrados durante la vigencia del contrato.', 'Garantía y reposición por parte del proveedor según cláusula contractual.', 'Laura Rodríguez', 'Trimestral'],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
