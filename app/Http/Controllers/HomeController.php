<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Feria;
use App\Models\Ocupacion;
use App\Models\Pabellon;
use App\Models\Stand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Obtener resumen de todas las ferias para el dashboard
     * Retorna los totales generales de cada feria para mostrar en gráfico de barras
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumenTodasFerias()
    {
        try {
            // Obtener solo las ferias activas ordenadas por fecha de evento descendente
            $ferias = Feria::where('estado_feria', 1)
                ->orderByFechaEvento()
                ->get();

            if ($ferias->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $resumenFerias = [];

            foreach ($ferias as $feria) {
                $idFeria = $feria->id_feria;

                // Obtener todos los stands de la feria
                $totalStands = Stand::where('feria', $idFeria)->count();

                // Obtener ocupaciones con contratos
                $ocupaciones = Ocupacion::where('id_feria', $idFeria)
                    ->with(['contrato'])
                    ->get();

                // Obtener todos los pagos aprobados agrupados por empresa
                $pagosPorEmpresa = DB::table('pago')
                    ->where('id_feria', $idFeria)
                    ->where('estado', 1)
                    ->select('id_empresa', DB::raw('SUM(monto) as total_pagado'))
                    ->groupBy('id_empresa')
                    ->pluck('total_pagado', 'id_empresa');

                // Obtener todos los contratos
                $todosContratos = Contrato::where('id_feria', $idFeria)
                    ->where('codigo_contrato', '>', 0)
                    ->orderBy('id_empresa', 'asc')
                    ->orderBy('codigo_contrato', 'asc')
                    ->get()
                    ->groupBy('id_empresa');

                // Calcular estado de pago dinámicamente
                $estadosPagoContratos = [];
                
                foreach ($todosContratos as $idEmpresa => $contratosEmpresa) {
                    $totalPagado = $pagosPorEmpresa->get($idEmpresa, 0);
                    $pagoAcumulado = 0;

                    foreach ($contratosEmpresa as $contrato) {
                        $totalContrato = $contrato->descuento > 0 
                            ? $contrato->precio_total_desc 
                            : $contrato->precio_total;

                        $pagoDisponible = $totalPagado - $pagoAcumulado;
                        
                        if ($pagoDisponible <= 0) {
                            $estadosPagoContratos[$contrato->id_contrato] = 0;
                        } else {
                            $pagoAplicado = min($pagoDisponible, $totalContrato);
                            $pagoAcumulado += $pagoAplicado;

                            if ($pagoAplicado >= $totalContrato) {
                                $estadosPagoContratos[$contrato->id_contrato] = 2;
                            } else {
                                $estadosPagoContratos[$contrato->id_contrato] = 1;
                            }
                        }
                    }
                }

                // Contadores
                $contadores = [
                    'pagado_100' => 0,
                    'pago_parcial' => 0,
                    'con_contrato' => 0,
                    'reservado' => 0,
                    'libres' => 0,
                ];

                $standsOcupados = 0;

                // Procesar ocupaciones
                foreach ($ocupaciones as $ocupacion) {
                    if ($ocupacion->contrato) {
                        $contrato = $ocupacion->contrato;
                        $idContrato = $contrato->id_contrato;

                        $pago = $estadosPagoContratos[$idContrato] ?? 0;
                        $estadoReserva = (int)($contrato->estado_reserva ?? 0);

                        if ($pago > 0) {
                            if ($pago == 1) {
                                $contadores['pago_parcial']++;
                            } else {
                                $contadores['pagado_100']++;
                            }
                        } elseif ($estadoReserva == 3) {
                            $contadores['con_contrato']++;
                        } elseif ($estadoReserva == 2) {
                            $contadores['reservado']++;
                        }

                        $standsOcupados++;
                    }
                }

                $contadores['libres'] = $totalStands - $standsOcupados;

                $resumenFerias[] = [
                    'id_feria' => $feria->id_feria,
                    'nombre_feria' => $feria->nombre_feria,
                    'pagado_100' => $contadores['pagado_100'],
                    'pago_parcial' => $contadores['pago_parcial'],
                    'con_contrato' => $contadores['con_contrato'],
                    'reservado' => $contadores['reservado'],
                    'libres' => $contadores['libres'],
                    'total_stands' => $totalStands,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resumenFerias,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener resumen de una feria específica para el dashboard
     * Agrupa los datos por pabellón para mostrar en gráfico de barras
     * 
     * @param int $idFeria ID de la feria
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumenPorFeria($idFeria)
    {
        try {
            // Validar que la feria existe
            $feria = Feria::find($idFeria);
            
            if (!$feria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feria no encontrada',
                ], 404);
            }

            // Obtener todos los pabellones de la feria
            $pabellones = Pabellon::where('feria', $idFeria)
                ->orderBy('nombre_pabellon', 'asc')
                ->get();

            // Obtener todos los stands de la feria agrupados por pabellón
            $standsMap = Stand::whereIn('id_pabellon', $pabellones->pluck('id_pabellon'))
                ->where('feria', $idFeria)
                ->get()
                ->groupBy('id_pabellon');

            // Obtener todas las ocupaciones de la feria con contratos
            $ocupaciones = Ocupacion::where('id_feria', $idFeria)
                ->with(['contrato.empresa'])
                ->get()
                ->groupBy('id_stand');

            // Obtener todos los pagos aprobados de la feria agrupados por empresa
            $pagosPorEmpresa = DB::table('pago')
                ->where('id_feria', $idFeria)
                ->where('estado', 1) // Solo aprobados
                ->select('id_empresa', DB::raw('SUM(monto) as total_pagado'))
                ->groupBy('id_empresa')
                ->pluck('total_pagado', 'id_empresa');

            // Obtener todos los contratos de la feria
            $todosContratos = Contrato::where('id_feria', $idFeria)
                ->where('codigo_contrato', '>', 0)
                ->orderBy('id_empresa', 'asc')
                ->orderBy('codigo_contrato', 'asc')
                ->get()
                ->groupBy('id_empresa');

            // Calcular estado de pago dinámicamente para cada contrato
            $estadosPagoContratos = [];
            
            foreach ($todosContratos as $idEmpresa => $contratosEmpresa) {
                $totalPagado = $pagosPorEmpresa->get($idEmpresa, 0);
                $pagoAcumulado = 0;

                foreach ($contratosEmpresa as $contrato) {
                    $totalContrato = $contrato->descuento > 0 
                        ? $contrato->precio_total_desc 
                        : $contrato->precio_total;

                    $pagoDisponible = $totalPagado - $pagoAcumulado;
                    
                    if ($pagoDisponible <= 0) {
                        $estadosPagoContratos[$contrato->id_contrato] = 0; // Sin pago
                    } else {
                        $pagoAplicado = min($pagoDisponible, $totalContrato);
                        $pagoAcumulado += $pagoAplicado;

                        if ($pagoAplicado >= $totalContrato) {
                            $estadosPagoContratos[$contrato->id_contrato] = 2; // Pago completo
                        } else {
                            $estadosPagoContratos[$contrato->id_contrato] = 1; // Pago parcial
                        }
                    }
                }
            }

            // Procesar cada pabellón
            $resumenPabellones = [];
            $totales = [
                'total_stands' => 0,
                'pagado_100' => 0,
                'pago_parcial' => 0,
                'con_contrato' => 0,
                'reservado' => 0,
                'libres' => 0,
            ];

            foreach ($pabellones as $pabellon) {
                $stands = $standsMap->get($pabellon->id_pabellon, collect());
                $totalStands = $stands->count();

                $estadisticas = [
                    'total_stands' => $totalStands,
                    'pagado_100' => 0,
                    'pago_parcial' => 0,
                    'con_contrato' => 0,
                    'reservado' => 0,
                    'libres' => 0,
                ];

                $standsOcupados = 0;

                // Procesar stands del pabellón
                foreach ($stands as $stand) {
                    $ocupacion = $ocupaciones->get($stand->id_stand)?->first();

                    if ($ocupacion && $ocupacion->contrato) {
                        $contrato = $ocupacion->contrato;
                        $idContrato = $contrato->id_contrato;

                        // Obtener estado de pago calculado dinámicamente
                        $pago = $estadosPagoContratos[$idContrato] ?? 0;
                        $estadoReserva = (int)($contrato->estado_reserva ?? 0);

                        if ($pago > 0) {
                            if ($pago == 1) {
                                $estadisticas['pago_parcial']++;
                            } else {
                                $estadisticas['pagado_100']++;
                            }
                        } elseif ($estadoReserva == 3) {
                            $estadisticas['con_contrato']++;
                        } elseif ($estadoReserva == 2) {
                            $estadisticas['reservado']++;
                        }

                        $standsOcupados++;
                    } else {
                        $estadisticas['libres']++;
                    }
                }

                $resumenPabellones[] = [
                    'id_pabellon' => $pabellon->id_pabellon,
                    'nombre_pabellon' => $pabellon->nombre_pabellon,
                    'pagado_100' => $estadisticas['pagado_100'],
                    'pago_parcial' => $estadisticas['pago_parcial'],
                    'con_contrato' => $estadisticas['con_contrato'],
                    'reservado' => $estadisticas['reservado'],
                    'libres' => $estadisticas['libres'],
                    'total_stands' => $totalStands,
                ];

                // Acumular totales generales
                $totales['total_stands'] += $estadisticas['total_stands'];
                $totales['pagado_100'] += $estadisticas['pagado_100'];
                $totales['pago_parcial'] += $estadisticas['pago_parcial'];
                $totales['con_contrato'] += $estadisticas['con_contrato'];
                $totales['reservado'] += $estadisticas['reservado'];
                $totales['libres'] += $estadisticas['libres'];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => [
                        'id' => $feria->id_feria,
                        'nombre' => $feria->nombre_feria,
                        'codigo' => $feria->codigo_contrato,
                        'estado' => $feria->estado_feria,
                    ],
                    'pabellones' => $resumenPabellones,
                    'totales' => $totales,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen: ' . $e->getMessage(),
            ], 500);
        }
    }
}
