<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteProductosExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithCustomStartCell, ShouldAutoSize
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function headings(): array
    {
        return [
            'Codigo',
            'Producto',
            'Tipo',
            'Area',
            'Unidad',
            'Stock',
            'Costo Total',
            'Estado',
            'Ubicacion',
        ];
    }

    public function map($reg): array
    {
        $esActivoFijo = strtolower((string) $reg->tipo) === 'activo fijo';
        $ubicacion = '-';

        if ($esActivoFijo) {
            $estadoMovimiento = (int) $reg->estado_movimiento;
            if ($estadoMovimiento === 0 || $estadoMovimiento === 1) {
                $ubicacion = 'En Almacen';
            } else {
                $personaRecibe = $reg->persona_recibe ?: 'No especificado';
                $ubicacion = 'Fuera del Almacen - ' . $personaRecibe;
            }
        }

        return [
            $reg->codigo,
            $reg->producto_nombre ?: 'Sin Nombre',
            $reg->tipo ?: '-',
            $reg->area_nombre ?: 'Sin Area',
            $reg->unidad_medida ?: '-',
            (float) $reg->stock,
            (float) $reg->costo_total,
            $reg->estado_dado_baja == 1 ? 'Dado de Baja' : 'Activo',
            $ubicacion,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            3 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4F81BD'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $delegate = $event->sheet->getDelegate();
                $highestRow = max(3, $delegate->getHighestDataRow());

                $delegate->mergeCells('A1:I1');
                $delegate->setCellValue('A1', 'REPORTE DE PRODUCTOS');
                $delegate->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 18,
                        'color' => ['argb' => 'FF000000'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $delegate->getRowDimension(1)->setRowHeight(30);

                $delegate->getStyle('A3:I' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $delegate->setAutoFilter('A3:I3');
                $delegate->freezePane('A4');

                $delegate->getStyle('F4:F' . $highestRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                $delegate->getStyle('G4:G' . $highestRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            },
        ];
    }
}
