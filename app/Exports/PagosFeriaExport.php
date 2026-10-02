<?php

namespace App\Exports;

use App\Models\Feria;
use App\Models\Pago;
use App\Models\Stand;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PagosFeriaExport implements WithMultipleSheets
{
    protected $id_feria;
    protected $aux_array = [];
    protected $aux_array2 = [];
    protected $z;

    public function __construct($id_feria)
    {
        $this->id_feria = $id_feria;
        $this->cargarDatos();
    }

    public function sheets(): array
    {
        $today = now()->format('Y-m-d');
        return [
            new ListadoPagosSheet($this->aux_array, $today),
            new DetallePagosSheet($this->aux_array2, $today),
        ];
    }

    protected function cargarDatos()
    {
        $id_feria = $this->id_feria;

        // Obtener evento usando modelo
        $this->z = Feria::where('id_feria', $id_feria)
            ->select('estado_feria', 'nombre_feria', 'codigo_factura')
            ->first();

        set_time_limit(300);

        // OPTIMIZACIÓN: Cargar todos los pagos aprobados de una vez
        $pagosPorEmpresa = Pago::where('id_feria', $id_feria)
            ->where('estado', 1)
            ->select('id_empresa', DB::raw('SUM(monto) as total_monto'))
            ->groupBy('id_empresa')
            ->pluck('total_monto', 'id_empresa')
            ->toArray();

        // OPTIMIZACIÓN: Cargar todos los stands necesarios de una vez
        $standsCache = Stand::select('id_stand', 'numero_stand')
            ->get()
            ->keyBy('id_stand')
            ->toArray();

        // Obtener datos base de contratos
        $aux = DB::table('empresas as e')
            ->join('contrato as c', 'e.id_empresa', '=', 'c.id_empresa')
            ->join('stands as s', 's.id_stand', '=', 'c.stand_1')
            ->join('pabellones as p', 'p.id_pabellon', '=', 's.id_pabellon')
            ->where('c.id_feria', '=', $id_feria)
            ->where('c.codigo_contrato', '>', '0')
            ->select(
                'e.nombre_empresa',
                'c.codigo_contrato',
                'c.fecha_realizacion',
                'e.id_empresa',
                's.numero_stand',
                'e.email',
                'e.web',
                'c.precio_unit',
                'p.nombre_pabellon',
                'c.total_stands',
                'c.stand_1',
                'c.stand_2',
                'c.stand_3',
                'c.stand_4',
                'c.stand_5',
                'c.stand_6',
                'c.stand_7',
                'c.stand_8',
                'c.stand_9',
                'c.stand_10',
                'c.stand_11',
                'c.stand_12',
                'c.metraje_total',
                'c.precio_total',
                'c.descuento',
                'c.precio_total_desc',
                'c.tipo_desc',
                'e.nit',
                'c.fecha_final'
            )
            ->orderBy('c.codigo_contrato', 'asc')
            ->get();

        // PROCESAR PRIMER SHEET: LISTADO PAGOS
        $pagosAcumulados = [];
        
        foreach ($aux as $a) {
            // OPTIMIZACIÓN: Concatenar stands usando cache en memoria
            if ($a->total_stands > 1) {
                $standsIds = [
                    $a->stand_2, $a->stand_3, $a->stand_4, $a->stand_5, $a->stand_6,
                    $a->stand_7, $a->stand_8, $a->stand_9, $a->stand_10, $a->stand_11, $a->stand_12
                ];
                
                for ($i = 0; $i < ($a->total_stands - 1); $i++) {
                    if (isset($standsCache[$standsIds[$i]])) {
                        $a->numero_stand .= ", " . $standsCache[$standsIds[$i]]['numero_stand'];
                    }
                }
            }

            // Determinar el total a pagar del contrato actual
            $totalContrato = $a->descuento > 0 ? $a->precio_total_desc : $a->precio_total;

            // Obtener el total de pagos de la empresa
            $totalPagosEmpresa = $pagosPorEmpresa[$a->id_empresa] ?? 0;
            
            // Obtener cuánto ya se distribuyó en contratos anteriores
            if (!isset($pagosAcumulados[$a->id_empresa])) {
                $pagosAcumulados[$a->id_empresa] = 0;
            }
            
            $pagoYaDistribuido = $pagosAcumulados[$a->id_empresa];
            
            // Calcular cuánto pago queda disponible para este contrato
            $pagoDisponible = $totalPagosEmpresa - $pagoYaDistribuido;
            
            // El pago aplicado a este contrato es el menor entre:
            // 1. Lo que falta pagar del contrato
            // 2. Lo que queda disponible del pago total
            $dife = 0;
            if ($pagoDisponible > 0) {
                $dife = min($pagoDisponible, $totalContrato);
                $pagosAcumulados[$a->id_empresa] += $dife;
            }

            // Calcular deuda
            $deuda = $totalContrato - $dife;

            $this->aux_array[] = [
                "CONTRATO" => $a->codigo_contrato,
                "EMPRESA" => $a->nombre_empresa,
                "PABELLON" => $a->nombre_pabellon,
                "F_CONTRATO" => $a->fecha_realizacion,
                "NRO. STANDS" => $a->total_stands,
                "CANT STANDS" => $a->numero_stand,
                "M2" => $a->metraje_total,
                "COSTO M2" => $a->precio_unit,
                "T.CONTRATO" => $a->precio_total,
                "TIPO DESCUENTO" => $a->tipo_desc,
                "% DESCUENTO" => $a->descuento,
                "T.A PAGAR" => $totalContrato,
                "PAGO" => $dife,
                "DEUDA" => $deuda,
                "S.PAGO" => $a->fecha_final
            ];
        }

        // PROCESAR SEGUNDO SHEET: DETALLE PAGOS
        // OPTIMIZACIÓN: Cargar todos los pagos y usuarios de una vez con eager loading
        $pagosDetalle = Pago::where('id_feria', $id_feria)
            ->with(['usuario:id_usuario,nombre_usuario', 'usuarioAprobacion:id_usuario,nombre_usuario'])
            ->select('id', 'id_empresa', 'id_feria', 'monto', 'tipo_pago', 'fecha', 'fecha_aprobacion', 'extension', 'id_usuario', 'id_aprobacion', 'estado')
            ->get()
            ->groupBy('id_empresa');

        // Agrupar contratos y pabellones por empresa
        $contratosPorEmpresa = collect($aux)->groupBy('id_empresa')->map(function ($items) {
            $first = $items->first();
            return [
                'contratos' => $items->pluck('codigo_contrato')->implode(', '),
                'pabellones' => $items->pluck('nombre_pabellon')->unique()->implode(' - '),
                'nombre_empresa' => $first->nombre_empresa
            ];
        });

        foreach ($contratosPorEmpresa as $idEmpresa => $data) {
            $pagosEmpresa = $pagosDetalle->get($idEmpresa, collect([]));

            foreach ($pagosEmpresa as $b) {
                $registrado_nombre = $b->usuario->nombre_usuario ?? '';
                $aprobado_nombre = $b->usuarioAprobacion->nombre_usuario ?? '';

                // Determinar tipo de pago y link
                $tipo_pago_mapping = [
                    0 => "Deposito",
                    1 => "Efectivo",
                    2 => "Cheque"
                ];
                
                $tipo_pago = $tipo_pago_mapping[$b->tipo_pago] ?? "Desconocido";
                
                $link = match ($b->tipo_pago) {
                    0 => url("/img/pagos/{$b->id}.{$b->extension}"),
                    1 => "Pago Realizado En Efectivo",
                    2 => url("/img/pagos/{$b->id}.{$b->extension}"),
                    default => ""
                };

                $this->aux_array2[] = [
                    "CONTRATO" => $data['contratos'],
                    "EMPRESA" => $data['nombre_empresa'],
                    "PABELLON" => $data['pabellones'],
                    "MONTO" => $b->monto,
                    "T.PAGO" => $tipo_pago,
                    "F.REGISTRO" => $b->fecha,
                    "REGISTRADO" => $registrado_nombre,
                    "F.APROBADO" => $b->fecha_aprobacion,
                    "APROBADO" => $aprobado_nombre,
                    "LINK" => $link
                ];
            }
        }
    }
}

class ListadoPagosSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected $data;
    protected $sheetName;

    public function __construct($data, $today)
    {
        $this->data = $data;
        $this->sheetName = 'Listado de Pagos ' . $today;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            "CONTRATO",
            "EMPRESA",
            "PABELLON",
            "F_CONTRATO",
            "NRO. STANDS",
            "CANT STANDS",
            "M2",
            "COSTO M2",
            "T.CONTRATO",
            "TIPO DESCUENTO",
            "% DESCUENTO",
            "T.A PAGAR",
            "PAGO",
            "DEUDA",
            "S.PAGO"
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF000000']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'border' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        for ($col = 1; $col <= 15; $col++) {
            $sheet->getCellByColumnAndRow($col, 1)->getStyle()->applyFromArray($headerStyle);
        }

        $columnWidths = [12, 18, 15, 12, 12, 15, 10, 12, 12, 15, 12, 12, 10, 10, 12];
        foreach ($columnWidths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $sheet->setAutoFilter('A1:O1');

        return [];
    }
}

class DetallePagosSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    protected $data;
    protected $sheetName;

    public function __construct($data, $today)
    {
        $this->data = $data;
        $this->sheetName = 'Detalles de Pagos ' . $today;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            "CONTRATO",
            "EMPRESA",
            "PABELLON",
            "MONTO",
            "T.PAGO",
            "F.REGISTRO",
            "REGISTRADO",
            "F.APROBADO",
            "APROBADO",
            "LINK"
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF000000']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'border' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        for ($col = 1; $col <= 10; $col++) {
            $sheet->getCellByColumnAndRow($col, 1)->getStyle()->applyFromArray($headerStyle);
        }

        $columnWidths = [12, 18, 15, 12, 12, 12, 15, 12, 15, 25];
        foreach ($columnWidths as $index => $width) {
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
        }

        $sheet->setAutoFilter('A1:J1');

        return [];
    }
}
