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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AcreditadosExport implements FromQuery, ShouldAutoSize, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private Builder $query,
        private string $nombreFeria,
        private string $fechaExportacion,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Empresa', 'Nombre completo', 'CI', 'Teléfono', 'Email', 'Tipo de credencial', 'Estado'];
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function map($acreditado): array
    {
        return [
            $acreditado->nombre_empresa,
            $acreditado->nombre,
            trim($acreditado->ci.' '.($acreditado->exp ?? '')),
            $acreditado->celular ?: 'Sin datos',
            $acreditado->email ?: $acreditado->com_email ?: 'Sin datos',
            match ((int) $acreditado->tipo_credencial) {
                1 => 'Expositor', 2 => 'Prensa', 3 => 'Oficial', 4 => 'Servicios', 5 => 'Negocios',
                default => 'Sin tipo definido',
            },
            match ((int) $acreditado->estado_credencial) {
                0 => 'Inhabilitado', 1 => 'Activo', 2 => 'Anulado', 3 => 'Eliminado',
                default => 'Sin estado definido',
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC12D73']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = max(4, $sheet->getHighestDataRow());

                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A1', 'ACREDITADOS - '.$this->nombreFeria);
                $sheet->setCellValue('A2', 'Fecha de exportación: '.$this->fechaExportacion);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF2D2A3A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['argb' => 'FF6B6878']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->setAutoFilter('A4:G'.$highestRow);
                $sheet->freezePane('A5');
            },
        ];
    }
}
