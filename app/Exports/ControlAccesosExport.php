<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ControlAccesosExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $resumen,
        private readonly Builder $detalle
    ) {}

    public function sheets(): array
    {
        return [
            new ControlAccesosResumenSheet($this->resumen),
            new ControlAccesosDetalleSheet($this->detalle),
        ];
    }
}

class ControlAccesosResumenSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tipo de credencial', 'Fecha', 'Ingresos'];
    }

    public function title(): string
    {
        return 'Resumen diario';
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 16, 'C' => 16];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '205493']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:C1');
            },
        ];
    }
}

class ControlAccesosDetalleSheet extends DefaultValueBinder implements FromQuery, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private readonly Builder $rows) {}

    public function query(): Builder
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Fecha', 'Hora', 'Puerta', 'Nombre completo', 'CI', 'Empresa'];
    }

    public function map($row): array
    {
        $fecha = CarbonImmutable::parse($row->fecha_ingreso);

        return [
            $fecha->format('d/m/Y'),
            $fecha->format('H:i:s'),
            (string) ($row->numero_puerta ?? $row->punto_acceso ?? ''),
            (string) ($row->nombre_completo ?? ''),
            (string) ($row->ci ?? ''),
            (string) ($row->nombre_empresa ?? ''),
        ];
    }

    public function title(): string
    {
        return 'Detalle de ingresos';
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 14, 'C' => 20, 'D' => 32, 'E' => 20, 'F' => 30];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '205493']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:F1');
            },
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
