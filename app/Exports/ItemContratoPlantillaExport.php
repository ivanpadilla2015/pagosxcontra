<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemContratoPlantillaExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /** @var Collection */
    private $productos;

    public function __construct(Collection $productos)
    {
        $this->productos = $productos;
    }

    public function headings(): array
    {
        return [
            'Nombre Producto',
            'Codigo Uso',
            'Tipo',
            'Valor Costo',
            'IVA',
            'Valor Total (con IVA)',
        ];
    }

    public function collection(): Collection
    {
        return $this->productos->map(function ($producto) {
            return [
                $producto->name,
                $producto->uso->codigo_uso ?? '',
                $producto->tipo ?? 'bien',
                '',
                '',
                '',
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
