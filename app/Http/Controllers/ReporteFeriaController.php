<?php

namespace App\Http\Controllers;

use App\Models\Feria;
use App\Models\Pabellon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReporteFeriaController extends Controller
{
    /**
     * Obtener reporte de feria (total o por pabellon).
     */
    public function show(Request $request, $id)
    {
        $feria = Feria::find($id);

        if (!$feria) {
            return response()->json([
                'success' => false,
                'message' => 'Feria no encontrada',
            ], 404);
        }

        $pabellones = Pabellon::where('feria', $id)
            ->orderBy('nombre_pabellon', 'asc')
            ->get(['id_pabellon', 'nombre_pabellon', 'feria']);

        $pabellonId = $request->query('pabellon_id');
        $pabellonSeleccionado = null;

        if (!is_null($pabellonId)) {
            $pabellonSeleccionado = $pabellones->firstWhere('id_pabellon', (int) $pabellonId);

            if (!$pabellonSeleccionado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pabellon no encontrado para la feria indicada',
                ], 404);
            }
        }

        $totalStandsQuery = DB::table('stands')
            ->where('feria', $id);

        if ($pabellonSeleccionado) {
            $totalStandsQuery->where('id_pabellon', $pabellonSeleccionado->id_pabellon);
        }

        $totalStands = (int) $totalStandsQuery->count();

        $pagosPorEmpresa = DB::table('pago')
            ->where('id_feria', $id)
            ->where('estado', 1)
            ->select('id_empresa', DB::raw('SUM(monto) as total_pagado'))
            ->groupBy('id_empresa')
            ->pluck('total_pagado', 'id_empresa');

        $contratosEmpresa = DB::table('contrato')
            ->where('id_feria', $id)
            ->where('codigo_contrato', '>', 0)
            ->orderBy('id_empresa', 'asc')
            ->orderBy('codigo_contrato', 'asc')
            ->get(['id_contrato', 'id_empresa', 'precio_total', 'precio_total_desc', 'descuento'])
            ->groupBy('id_empresa');

        $estadosPagoContratos = [];

        foreach ($contratosEmpresa as $idEmpresa => $contratos) {
            $totalPagado = (float) $pagosPorEmpresa->get($idEmpresa, 0);
            $pagoAcumulado = 0;

            foreach ($contratos as $contratoEmpresa) {
                $totalContrato = (float) ($contratoEmpresa->descuento > 0
                    ? $contratoEmpresa->precio_total_desc
                    : $contratoEmpresa->precio_total);

                $pagoDisponible = $totalPagado - $pagoAcumulado;

                if ($pagoDisponible <= 0) {
                    $estadosPagoContratos[$contratoEmpresa->id_contrato] = 0;
                } else {
                    $pagoAplicado = min($pagoDisponible, $totalContrato);
                    $pagoAcumulado += $pagoAplicado;

                    $estadosPagoContratos[$contratoEmpresa->id_contrato] = $pagoAplicado >= $totalContrato ? 2 : 1;
                }
            }
        }

        $standsDetalleQuery = DB::table('stands as s')
            ->leftJoin('ocupaciones as o', 'o.id_stand', '=', 's.id_stand')
            ->leftJoin('contrato as c', 'c.id_contrato', '=', 'o.id_contrato')
            ->where('s.feria', $id);

        if ($pabellonSeleccionado) {
            $standsDetalleQuery->where('s.id_pabellon', $pabellonSeleccionado->id_pabellon);
        }

        $standsDetalle = $standsDetalleQuery
            ->get(['s.id_stand', 'c.id_contrato', 'c.estado_reserva']);

        $reservados = 0;
        $contrato = 0;
        $pagoParcial = 0;
        $pagadoTotal = 0;
        $libres = 0;

        foreach ($standsDetalle as $stand) {
            if (!$stand->id_contrato) {
                $libres++;
                continue;
            }

            $pago = $estadosPagoContratos[$stand->id_contrato] ?? 0;
            $estadoReserva = (int) ($stand->estado_reserva ?? 0);

            if ($pago > 0) {
                if ($pago == 1) {
                    $pagoParcial++;
                } else {
                    $pagadoTotal++;
                }
            } elseif ($estadoReserva == 3) {
                $contrato++;
            } elseif ($estadoReserva == 2) {
                $reservados++;
            }
        }

        $porcentajesStands = [
            'reservados' => $totalStands > 0 ? round(($reservados / $totalStands) * 100, 2) : 0,
            'contrato' => $totalStands > 0 ? round(($contrato / $totalStands) * 100, 2) : 0,
            'pago_parcial' => $totalStands > 0 ? round(($pagoParcial / $totalStands) * 100, 2) : 0,
            'pagado_total' => $totalStands > 0 ? round(($pagadoTotal / $totalStands) * 100, 2) : 0,
            'libres' => $totalStands > 0 ? round(($libres / $totalStands) * 100, 2) : 0,
        ];

        $contratosScope = $pabellonSeleccionado
            ? DB::table('ocupaciones as o')
                ->join('stands as s', 's.id_stand', '=', 'o.id_stand')
                ->join('contrato as c', 'c.id_contrato', '=', 'o.id_contrato')
                ->where('o.id_feria', $id)
                ->where('s.id_pabellon', $pabellonSeleccionado->id_pabellon)
                ->where('c.estado_reserva', 3)
                ->distinct('o.id_contrato')
                ->pluck('o.id_contrato')
            : DB::table('contrato')
                ->where('id_feria', $id)
                ->where('estado_reserva', 3)
                ->pluck('id_contrato');

        $totalContratoGenerado = (float) DB::table('contrato')
            ->whereIn('id_contrato', $contratosScope)
            ->selectRaw('COALESCE(SUM(precio_total_desc), 0) as total')
            ->value('total');

        $empresasScope = DB::table('contrato')
            ->whereIn('id_contrato', $contratosScope)
            ->distinct('id_empresa')
            ->pluck('id_empresa');

        $ingresoParcial = (float) DB::table('pago')
            ->where('id_feria', $id)
            ->where('estado', 1)
            ->when($pabellonSeleccionado, function ($query) use ($empresasScope) {
                $query->whereIn('id_empresa', $empresasScope);
            })
            ->sum('monto');

        $pendienteCobro = max($totalContratoGenerado - $ingresoParcial, 0);

        $porcentajesPagos = [
            'ingreso_parcial' => $totalContratoGenerado > 0 ? round(($ingresoParcial / $totalContratoGenerado) * 100, 2) : 0,
            'pendiente_cobro' => $totalContratoGenerado > 0 ? round(($pendienteCobro / $totalContratoGenerado) * 100, 2) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'feria' => [
                    'id_feria' => $feria->id_feria,
                    'nombre_feria' => $feria->nombre_feria,
                ],
                'pabellones' => $pabellones,
                'titulo' => $pabellonSeleccionado ? $pabellonSeleccionado->nombre_pabellon : 'Reporte Total',
                'resumen_stands' => [
                    'total_stands' => $totalStands,
                    'reservados' => $reservados,
                    'contrato' => $contrato,
                    'pago_parcial' => $pagoParcial,
                    'pagado_total' => $pagadoTotal,
                    'libres' => $libres,
                    'porcentajes' => $porcentajesStands,
                ],
                'resumen_pagos' => [
                    'total_contrato_generado' => $totalContratoGenerado,
                    'ingreso_parcial' => $ingresoParcial,
                    'pendiente_cobro' => $pendienteCobro,
                    'porcentajes' => $porcentajesPagos,
                ],
            ],
        ]);
    }

    /**
     * Descargar reporte en PDF.
     */
    public function downloadPDF(Request $request, $id)
    {
        $feria = Feria::find($id);

        if (!$feria) {
            return response()->json([
                'success' => false,
                'message' => 'Feria no encontrada',
            ], 404);
        }

        $pabellones = Pabellon::where('feria', $id)
            ->orderBy('nombre_pabellon', 'asc')
            ->get(['id_pabellon', 'nombre_pabellon', 'feria']);

        $pabellonId = $request->query('pabellon_id');
        $pabellonSeleccionado = null;

        if (!is_null($pabellonId)) {
            $pabellonSeleccionado = $pabellones->firstWhere('id_pabellon', (int) $pabellonId);
            if (!$pabellonSeleccionado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pabellon no encontrado',
                ], 404);
            }
        }

        // Obtener datos del reporte (reutilizar lógica)
        $totalStandsQuery = DB::table('stands')->where('feria', $id);
        if ($pabellonSeleccionado) {
            $totalStandsQuery->where('id_pabellon', $pabellonSeleccionado->id_pabellon);
        }
        $totalStands = (int) $totalStandsQuery->count();

        $pagosPorEmpresa = DB::table('pago')
            ->where('id_feria', $id)
            ->where('estado', 1)
            ->select('id_empresa', DB::raw('SUM(monto) as total_pagado'))
            ->groupBy('id_empresa')
            ->pluck('total_pagado', 'id_empresa');

        $contratosEmpresa = DB::table('contrato')
            ->where('id_feria', $id)
            ->where('codigo_contrato', '>', 0)
            ->orderBy('id_empresa', 'asc')
            ->orderBy('codigo_contrato', 'asc')
            ->get(['id_contrato', 'id_empresa', 'precio_total', 'precio_total_desc', 'descuento'])
            ->groupBy('id_empresa');

        $estadosPagoContratos = [];
        foreach ($contratosEmpresa as $idEmpresa => $contratos) {
            $totalPagado = (float) $pagosPorEmpresa->get($idEmpresa, 0);
            $pagoAcumulado = 0;
            foreach ($contratos as $contratoEmpresa) {
                $totalContrato = (float) ($contratoEmpresa->descuento > 0
                    ? $contratoEmpresa->precio_total_desc
                    : $contratoEmpresa->precio_total);
                $pagoDisponible = $totalPagado - $pagoAcumulado;
                if ($pagoDisponible <= 0) {
                    $estadosPagoContratos[$contratoEmpresa->id_contrato] = 0;
                } else {
                    $pagoAplicado = min($pagoDisponible, $totalContrato);
                    $pagoAcumulado += $pagoAplicado;
                    $estadosPagoContratos[$contratoEmpresa->id_contrato] = $pagoAplicado >= $totalContrato ? 2 : 1;
                }
            }
        }

        $standsDetalleQuery = DB::table('stands as s')
            ->leftJoin('ocupaciones as o', 'o.id_stand', '=', 's.id_stand')
            ->leftJoin('contrato as c', 'c.id_contrato', '=', 'o.id_contrato')
            ->where('s.feria', $id);
        if ($pabellonSeleccionado) {
            $standsDetalleQuery->where('s.id_pabellon', $pabellonSeleccionado->id_pabellon);
        }

        $standsDetalle = $standsDetalleQuery->get(['s.id_stand', 'c.id_contrato', 'c.estado_reserva']);

        $reservados = 0;
        $contrato = 0;
        $pagoParcial = 0;
        $pagadoTotal = 0;
        $libres = 0;

        foreach ($standsDetalle as $stand) {
            if (!$stand->id_contrato) {
                $libres++;
                continue;
            }
            $pago = $estadosPagoContratos[$stand->id_contrato] ?? 0;
            $estadoReserva = (int) ($stand->estado_reserva ?? 0);
            if ($pago > 0) {
                if ($pago == 1) {
                    $pagoParcial++;
                } else {
                    $pagadoTotal++;
                }
            } elseif ($estadoReserva == 3) {
                $contrato++;
            } elseif ($estadoReserva == 2) {
                $reservados++;
            }
        }

        $contratosScope = $pabellonSeleccionado
            ? DB::table('ocupaciones as o')
                ->join('stands as s', 's.id_stand', '=', 'o.id_stand')
                ->join('contrato as c', 'c.id_contrato', '=', 'o.id_contrato')
                ->where('o.id_feria', $id)
                ->where('s.id_pabellon', $pabellonSeleccionado->id_pabellon)
                ->where('c.estado_reserva', 3)
                ->distinct('o.id_contrato')
                ->pluck('o.id_contrato')
            : DB::table('contrato')
                ->where('id_feria', $id)
                ->where('estado_reserva', 3)
                ->pluck('id_contrato');

        $totalContratoGenerado = (float) DB::table('contrato')
            ->whereIn('id_contrato', $contratosScope)
            ->selectRaw('COALESCE(SUM(precio_total_desc), 0) as total')
            ->value('total');

        $empresasScope = DB::table('contrato')
            ->whereIn('id_contrato', $contratosScope)
            ->distinct('id_empresa')
            ->pluck('id_empresa');

        $ingresoParcial = (float) DB::table('pago')
            ->where('id_feria', $id)
            ->where('estado', 1)
            ->when($pabellonSeleccionado, function ($query) use ($empresasScope) {
                $query->whereIn('id_empresa', $empresasScope);
            })
            ->sum('monto');

        $pendienteCobro = max($totalContratoGenerado - $ingresoParcial, 0);

        // Generar PDF
        $pdf = new ReportePDF();
        $pdf->feria = $feria->nombre_feria;
        $pdf->titulo = $pabellonSeleccionado ? $pabellonSeleccionado->nombre_pabellon : 'Total';
        $pdf->fecha = date('d/m/Y H:i:s');
        $pdf->AddPage();

        // Se resume de Stands
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, 'RESUMEN DE STANDS', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        
        // Tabla de stands
        $pdf->SetFillColor(200, 200, 200);
        $colWidths = [110, 40, 40];
        $pdf->Cell($colWidths[0], 7, 'Concepto', 1, 0, 'C', true);
        $pdf->Cell($colWidths[1], 7, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 7, 'Porcentaje', 1, 1, 'C', true);
        
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 9);
        
        $standTableData = [
            ['Total Stands', $totalStands, '100.0%'],
            ['Reservados', $reservados, ($totalStands > 0 ? round(($reservados/$totalStands)*100, 1) : 0) . '%'],
            ['Con Contrato', $contrato, ($totalStands > 0 ? round(($contrato/$totalStands)*100, 1) : 0) . '%'],
            ['Pago Parcial', $pagoParcial, ($totalStands > 0 ? round(($pagoParcial/$totalStands)*100, 1) : 0) . '%'],
            ['Pagado 100%', $pagadoTotal, ($totalStands > 0 ? round(($pagadoTotal/$totalStands)*100, 1) : 0) . '%'],
            ['Libres', $libres, ($totalStands > 0 ? round(($libres/$totalStands)*100, 1) : 0) . '%'],
        ];
        
        foreach ($standTableData as $row) {
            $pdf->Cell($colWidths[0], 6, $row[0], 1, 0, 'L');
            $pdf->Cell($colWidths[1], 6, $row[1], 1, 0, 'C');
            $pdf->Cell($colWidths[2], 6, $row[2], 1, 1, 'C');
        }
        
        // Gráficos de barras para stands
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, utf8_decode('Visualización de Distribución'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        
        $barStartY = $pdf->GetY();
        $colors = [
            [100, 150, 200], // Azul - Reservados
            [255, 193, 7],   // Amarillo - Contrato
            [76, 175, 80],   // Verde - Pago Parcial
            [33, 150, 243],  // Azul claro - Pagado 100%
            [189, 189, 189], // Gris - Libres
        ];
        
        $standItems = [
            ['Reservados', $reservados, $totalStands],
            ['Con Contrato', $contrato, $totalStands],
            ['Pago Parcial', $pagoParcial, $totalStands],
            ['Pagado 100%', $pagadoTotal, $totalStands],
            ['Libres', $libres, $totalStands],
        ];
        
        foreach ($standItems as $idx => $item) {
            list($label, $value, $total) = $item;
            $percent = $total > 0 ? round(($value / $total) * 100, 1) : 0;
            
            $pdf->SetXY(20, $barStartY);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(50, 5, $label . ': ' . $percent . '%', 0, 0);
            
            // Barra
            $pdf->SetXY(72, $barStartY);
            list($r, $g, $b) = $colors[$idx];
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetFillColor($r, $g, $b);
            $barWidth = ($percent / 100) * 80;
            $pdf->Rect(72, $barStartY, $barWidth, 4, 'F');
            $pdf->SetDrawColor(0, 0, 0);
            
            $barStartY += 6;
        }

        $pdf->Ln(6);
        
        // Resumen de Balance
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, 'RESUMEN DE BALANCE GENERAL', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        
        // Tabla de balance
        $pdf->SetFillColor(200, 200, 200);
        $balColWidths = [100, 50, 40];
        $pdf->Cell($balColWidths[0], 7, 'Concepto', 1, 0, 'C', true);
        $pdf->Cell($balColWidths[1], 7, 'Monto (Bs)', 1, 0, 'C', true);
        $pdf->Cell($balColWidths[2], 7, 'Porcentaje', 1, 1, 'C', true);
        
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 9);
        
        $ingresoPercent = $totalContratoGenerado > 0 ? round(($ingresoParcial / $totalContratoGenerado) * 100, 1) : 0;
        $pendientePercent = $totalContratoGenerado > 0 ? round(($pendienteCobro / $totalContratoGenerado) * 100, 1) : 0;
        
        $balanceTableData = [
            ['Total Contrato Generado', $this->formatMonto($totalContratoGenerado), '100.0%'],
            ['Ingreso Parcial', $this->formatMonto($ingresoParcial), $ingresoPercent . '%'],
            ['Pendiente Cobro', $this->formatMonto($pendienteCobro), $pendientePercent . '%'],
        ];
        
        foreach ($balanceTableData as $row) {
            $pdf->Cell($balColWidths[0], 6, $row[0], 1, 0, 'L');
            $pdf->Cell($balColWidths[1], 6, $row[1], 1, 0, 'R');
            $pdf->Cell($balColWidths[2], 6, $row[2], 1, 1, 'C');
        }
        
        // Gráficos de barras para balance
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, utf8_decode('Visualización de Balance'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        
        $barStartY = $pdf->GetY();
        $balanceColors = [
            [255, 193, 7],   // Amarillo - Total Contrato
            [76, 175, 80],   // Verde - Ingreso Parcial
            [244, 67, 54],   // Rojo - Pendiente
        ];
        
        $balanceItems = [
            ['Total Contrato', $totalContratoGenerado, $totalContratoGenerado],
            ['Ingreso Parcial', $ingresoParcial, $totalContratoGenerado],
            ['Pendiente Cobro', $pendienteCobro, $totalContratoGenerado],
        ];
        
        foreach ($balanceItems as $idx => $item) {
            list($label, $value, $total) = $item;
            $percent = $total > 0 ? round(($value / $total) * 100, 1) : 0;
            $formatted = $this->formatMonto($value);
            
            $pdf->SetXY(20, $barStartY);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(50, 5, $label . ' (' . $percent . '%)', 0, 0);
            
            // Barra
            $pdf->SetXY(72, $barStartY);
            list($r, $g, $b) = $balanceColors[$idx];
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetFillColor($r, $g, $b);
            $barWidth = ($percent / 100) * 80;
            $pdf->Rect(72, $barStartY, $barWidth, 4, 'F');
            $pdf->SetDrawColor(0, 0, 0);
            
            // Monto
            $pdf->SetXY(155, $barStartY);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(0, 5, $formatted . ' Bs', 0, 1);
            
            $barStartY += 6;
        }

        $filename = 'reporte_' . Str::slug($pdf->titulo) . '_' . now()->format('YmdHis') . '.pdf';

        return response()->streamDownload(
            function () use ($pdf) {
                $pdf->Output();
            },
            $filename
        );
    }

    /**
     * Formatea monto con locale es_BO.
     */
    private function formatMonto($value)
    {
        $monto = (float) ($value ?? 0);
        return number_format($monto, 2, '.', ',');
    }
}

class ReportePDF extends \FPDF
{
    public $feria;
    public $titulo;
    public $fecha;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'Letter');
    }

    public function header()
    {
        // Logo FEXCO
        $logoPath = public_path('images/fexco.png');
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 8, 30);
        }

        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->SetXY(0, 15);
        $this->Cell(0, 10, 'REPORTE DE FERIA - ' . strtoupper($this->titulo), 0, 1, 'C');
        
        // Información
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Evento: ' . $this->feria, 0, 1, 'C');
        $this->Cell(0, 5, 'Fecha: ' . $this->fecha, 0, 1, 'C');
        $this->Ln(5);
    }

    public function footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->PageNo(), 0, 0, 'C');
    }
}
