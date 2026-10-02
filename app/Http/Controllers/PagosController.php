<?php

namespace App\Http\Controllers;

use App\Events\NotificacionAdminCreada;
use App\Events\RegistroActualizado;
use App\Models\NotificacionAdmin;
use App\Models\Pago;
use App\Models\Contrato;
use App\Models\Feria;
use App\Models\Usuario;
use App\Services\ReciboNumberService;
use App\Support\AreaPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PagosController extends Controller
{
    /**
     * Obtener todas las ferias activas
     */
    public function getFerias()
    {
        try {
            $ferias = Feria::select('id_feria', 'nombre_feria', 'codigo_contrato', 'estado_feria')
                ->orderByFechaEvento()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ferias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ferias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos agrupados por empresa para una feria específica
     * Con cálculo de totales y estado de pago
     * Adapta la lógica del sistema antiguo
     */
    public function getPagosbyFeria(Request $request, $idFeria)
    {
        try {
            // Validar feria
            $feria = Feria::select('id_feria', 'codigo_contrato', 'nombre_feria', 'estado_feria')
                ->find($idFeria);

            if (!$feria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feria no encontrada'
                ], 404);
            }

            // Obtener contratos válidos en esta feria
            // Estos representan el total contratado por empresa
            $contratos = Contrato::select(
                'id_contrato',
                'id_feria',
                'id_empresa',
                'codigo_contrato',
                'descuento',
                'precio_unit',
                'precio_total',
                'total_stands',
                'stand_1'
            )
                ->with(['empresa' => function ($query) {
                    $query->select('id_empresa', 'nombre_empresa', 'nit');
                }])
                ->where('id_feria', $idFeria)
                ->where('stand_1', '>', 0) // Tiene al menos un stand
                ->where('codigo_contrato', '>', 0) // Solo contratos generados
                ->orderBy('id_empresa', 'asc')
                ->get();

            // Obtener TODOS los pagos en esta feria (aprobados y pendientes)
            // estado: 0 = pendiente, 1 = aprobado
            $todosPagos = Pago::select(
                'id',
                'id_empresa',
                'id_feria',
                'fecha',
                'monto',
                'estado',
                'tipo_pago',
                'extension',
                'id_usuario',
                'id_aprobacion'
            )
                ->with([
                    'usuario:id_usuario,nombre_usuario,nick_usuario',
                    'usuarioAprobacion:id_usuario,nombre_usuario,nick_usuario',
                    'recibo:id_recibo,id_pago,numero_recibo,anio,tipo_pago,user_id',
                ])
                ->where('id_feria', $idFeria)
                ->orderBy('fecha', 'desc')
                ->get()
                ->groupBy('id_empresa'); // Agrupar por empresa

            // Procesar datos: agrupar por empresa y calcular totales
            $empresasAgrupadas = $contratos->groupBy('id_empresa');

            $empresasProcesadas = $empresasAgrupadas->map(function ($contratosEmpresa, $idEmpresa) use ($todosPagos, $feria) {
                // Información de la empresa
                $primerContrato = $contratosEmpresa->first();
                $empresa = $primerContrato->empresa;

                // Calcular total contratado para esta empresa
                // Suma de todos los precios totales de sus contratos con descuento aplicado
                $totalContratado = 0;
                foreach ($contratosEmpresa as $contrato) {
                    $precioTotal = (float)($contrato->precio_total ?? 0);
                    $descuento = (float)($contrato->descuento ?? 0);

                    // Si hay descuento, restar del precio total
                    if ($descuento > 0) {
                        $precioTotal = $precioTotal - ($precioTotal * ($descuento / 100));
                    }

                    $totalContratado += $precioTotal;
                }

                // Obtener pagos de esta empresa desde el array agrupado
                $pagosEmpresa = $todosPagos->get($idEmpresa) ?? collect();

                // Separar pagos aprobados y pendientes
                $pagosAprobados = $pagosEmpresa->filter(function ($p) {
                    return (int)$p->estado === 1;
                });

                $pagosPendientes = $pagosEmpresa->filter(function ($p) {
                    return (int)$p->estado === 0;
                });

                // Calcular total pagado (solo aprobados) - convertir monto a número
                $totalPagado = $pagosAprobados->sum(function ($pago) {
                    return (float) str_replace(['Bs. ', ',', ' '], '', $pago->monto ?? 0);
                });

                // Calcular saldo restante
                $saldo = $totalContratado - $totalPagado;

                // Determinar estado del pago
                $estatoPago = 'pendiente';
                if ($saldo <= 0) {
                    $estatoPago = 'pagado';
                } elseif ($saldo < $totalContratado && $totalPagado > 0) {
                    $estatoPago = 'parcial';
                }

                // Formatear pagos aprobados
                $pagosAprobadosFormato = $pagosAprobados->map(function ($pago) {
                    return [
                        'id' => $pago->id,
                        'fecha' => $pago->fecha,
                        'monto' => $this->normalizarMonto($pago->monto),
                        'tipo_pago' => $pago->tipo_pago,
                        'estado' => (int)$pago->estado,
                        'extension' => $pago->extension,
                        'usuario' => $pago->usuario ? $pago->usuario->nombre_usuario : 'N/A',
                        'nombre_reali' => $pago->usuario ? $pago->usuario->nombre_usuario : 'N/A',
                        'aprobador' => $pago->usuarioAprobacion ? $pago->usuarioAprobacion->nombre_usuario : 'N/A',
                        'nombre_apro' => $pago->usuarioAprobacion ? $pago->usuarioAprobacion->nombre_usuario : null,
                        'tiene_recibo' => (bool) $pago->recibo,
                        'numero_recibo' => $pago->recibo?->numero_recibo,
                    ];
                })->toArray();

                // Formatear pagos pendientes
                $pagosPendientesFormato = $pagosPendientes->map(function ($pago) {
                    return [
                        'id' => $pago->id,
                        'fecha' => $pago->fecha,
                        'monto' => $this->normalizarMonto($pago->monto),
                        'tipo_pago' => $pago->tipo_pago,
                        'estado' => (int)$pago->estado,
                        'extension' => $pago->extension,
                        'usuario' => $pago->usuario ? $pago->usuario->nombre_usuario : 'N/A',
                        'nombre_reali' => $pago->usuario ? $pago->usuario->nombre_usuario : 'N/A',
                        'tiene_recibo' => (bool) $pago->recibo,
                        'numero_recibo' => $pago->recibo?->numero_recibo,
                    ];
                })->toArray();

                // Formatear contratos de la empresa
                $contratosFormato = $contratosEmpresa->map(function ($contrato) {
                    return [
                        'id_contrato' => $contrato->id_contrato,
                        'codigo_contrato' => $contrato->codigo_contrato,
                        'precio_total' => $contrato->precio_total,
                        'descuento' => $contrato->descuento,
                    ];
                })->toArray();

                return [
                    'id_empresa' => $idEmpresa,
                    'nombre_empresa' => $empresa->nombre_empresa ?? 'Sin empresa',
                    'nit' => $empresa->nit ?? 'N/A',
                    'total_contratado' => round($totalContratado, 2),
                    'total_pagado' => round($totalPagado, 2),
                    'total_pendiente_aprobacion' => $pagosPendientes->sum(function ($p) {
                        return (float) str_replace(['Bs. ', ',', ' '], '', $p->monto ?? 0);
                    }),
                    'saldo' => round($saldo, 2),
                    'estado_pago' => $estatoPago,
                    'contratos' => $contratosFormato,
                    'pagos_aprobados' => $pagosAprobadosFormato,
                    'pagos_pendientes' => $pagosPendientesFormato,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => [
                        'id' => $feria->id_feria,
                        'nombre' => $feria->nombre_feria,
                        'codigo' => $feria->codigo_contrato,
                        'estado' => $feria->estado_feria,
                    ],
                    'empresas' => $empresasProcesadas,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar un nuevo pago
     * Requiere: pagos.create ability
     */
    public function store(Request $request)
    {
        // Validación adicional de permisos (bypass del usuario si es necesario)
        if (! \App\Support\AreaPermissions::allows($request->user(), 'pagos.create')) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'No autorizado para crear pagos');
        }

        $request->validate([
            'id_empresa' => 'required',
            'id_feria' => 'required',
            'monto' => 'required|numeric|min:0',
            'tipo_pago' => 'required',
            'foto' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'generar_recibo' => 'nullable|boolean',
        ]);

        $usuarioActual = $request->user();
        $foto = $request->file('foto');
        $extensionFoto = $foto ? strtolower((string) $foto->extension()) : null;
        if ($foto && ! in_array($extensionFoto, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            return response()->json(['message' => 'El formato del comprobante no es válido.'], 422);
        }

        $tipoPago = (int) $request->input('tipo_pago');
        $debeGenerarRecibo = $this->debeGenerarRecibo($tipoPago, $request->boolean('generar_recibo'));
        $reciboGenerado = null;
        $rutaFoto = null;

        DB::beginTransaction();
        try {
            $pago = new Pago();
            $pago->id_empresa = $request->id_empresa;
            $pago->id_feria = $request->id_feria;

            if (!is_numeric($request->id_feria)) {
                $pago->id_feria = 0;
            }

            $pago->id_usuario = $usuarioActual->id_usuario;
            $pago->id_aprobacion = 0;
            $pago->tipo_pago = $tipoPago;
            $pago->monto = $request->monto;
            $pago->fecha = now()->format('Y-m-d');
            $pago->estado = 0;

            // Guardar extensión si hay archivo
            if ($foto) {
                $pago->extension = $extensionFoto;
            }

            $pago->save();

            if ($foto) {
                $nombreArchivo = $pago->id . '.' . $extensionFoto;
                $rutaFoto = 'img/pagos/' . $nombreArchivo;
                File::ensureDirectoryExists(public_path('img/pagos'));
                $foto->move(public_path('img/pagos'), $nombreArchivo);
            }

            if ($debeGenerarRecibo) {
                $reciboService = app(ReciboNumberService::class);
                $reciboGenerado = $reciboService->generarParaPago($pago, (int) $usuarioActual->id_usuario);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($rutaFoto) {
                File::delete(public_path($rutaFoto));
                Storage::disk('local')->delete(str_replace('img/', '', $rutaFoto));
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago: ' . $e->getMessage(),
            ], 500);
        }

        $this->dispatchBroadcastSafely(new RegistroActualizado(
            $this->buildPagoBroadcastData($pago),
            'create',
            (int) $pago->id_feria,
        ));

        $this->notificarAdministracionPagoCreado($pago);

        event(new \App\Events\PagoRegistrado([
            'id' => (int) $pago->id,
            'empresa' => $pago->empresa?->nombre_empresa ?? 'Empresa',
            'referencia' => 'Pago #' . $pago->id,
            'monto' => number_format((float) $pago->monto, 2, ',', '.'),
            'fecha' => now()->format('d/m/Y'),
            'metodo' => $this->obtenerNombreTipoPago((int) $pago->tipo_pago),
            'usuario' => $usuarioActual->nombre_usuario ?? 'el sistema',
            'estado' => 'Registrado',
            'url' => url('/pagos/list'),
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'El pago ha sido registrado correctamente',
                'data' => [
                    'id_pago' => (int) $pago->id,
                    'tiene_recibo' => (bool) $reciboGenerado,
                    'numero_recibo' => $reciboGenerado?->numero_recibo,
                ],
            ]);
        }

        return redirect()
            ->route('pagos.index')
            ->with('success', 'El pago ha sido registrado correctamente');
    }

    public function archivo(Request $request, int $id)
    {
        $pago = Pago::query()->findOrFail($id);
        $extension = strtolower((string) $pago->extension);
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true), 404);

        $nombreArchivo = $pago->id . '.' . $extension;
        $rutaPrivada = 'pagos/' . $nombreArchivo;
        $disco = Storage::disk('local');
        $rutaPublica = public_path('img/pagos/' . $nombreArchivo);

        if (is_file($rutaPublica)) {
            $ruta = $rutaPublica;
        } elseif ($disco->exists($rutaPrivada)) {
            $ruta = $disco->path($rutaPrivada);
        } else {
            abort(404);
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        };
        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return $request->boolean('download')
            ? response()->download($ruta, $nombreArchivo, $headers)
            : response()->file($ruta, $headers);
    }

    /**
     * Aprobar un pago
     * Requiere: pagos.approve ability (solo Sistemas/Administración)
     */
    public function aprobar(Request $request, $id)
    {
        // Validación adicional de permisos
        if (! \App\Support\AreaPermissions::allows($request->user(), 'pagos.approve')) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'No autorizado para aprobar pagos');
        }

        try {
            DB::beginTransaction();
            
            $pago = Pago::findOrFail($id);
            $pago->estado = 1; // Aprobado
            $pago->fecha_aprobacion = now()->format('Y-m-d');
            $pago->id_aprobacion = $request->user()->id_usuario;
            $pago->save();

            // Actualizar el campo 'pago' en todos los contratos de esta empresa en esta feria
            $this->actualizarEstadoPagoContratos($pago->id_empresa, $pago->id_feria);

            $this->dispatchBroadcastSafely(new RegistroActualizado(
                $this->buildPagoBroadcastData($pago),
                'update',
                (int) $pago->id_feria,
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'El pago ha sido aprobado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estado de pago de los contratos de una empresa
     * Compara total pagado aprobado vs total de cada contrato
     * pago = 0: sin pagos
     * pago = 1: pago parcial
     * pago = 2+: pago completo
     */
    private function actualizarEstadoPagoContratos($idEmpresa, $idFeria)
    {
        // OPTIMIZACIÓN: Obtener total de pagos aprobados usando modelo
        $totalPagado = Pago::where('id_empresa', $idEmpresa)
            ->where('id_feria', $idFeria)
            ->where('estado', 1)
            ->sum('monto');

        // OPTIMIZACIÓN: Obtener contratos usando modelo con campos específicos
        $contratos = Contrato::where('id_empresa', $idEmpresa)
            ->where('id_feria', $idFeria)
            ->where('codigo_contrato', '>', 0)
            ->select('id_contrato', 'precio_total', 'precio_total_desc', 'descuento', 'codigo_contrato', 'pago')
            ->orderBy('codigo_contrato', 'asc')
            ->get();

        $pagoAcumulado = 0;

        foreach ($contratos as $contrato) {
            // Determinar total del contrato
            $totalContrato = $contrato->descuento > 0 
                ? $contrato->precio_total_desc 
                : $contrato->precio_total;

            // Calcular cuánto pago disponible hay para este contrato
            $pagoDisponible = $totalPagado - $pagoAcumulado;

            if ($pagoDisponible <= 0) {
                $contrato->pago = 0;
            } else {
                $pagoAplicado = min($pagoDisponible, $totalContrato);
                $pagoAcumulado += $pagoAplicado;

                if ($pagoAplicado >= $totalContrato) {
                    $contrato->pago = 2;
                } else {
                    $contrato->pago = 1;
                }
            }

            $contrato->save();
        }
    }

    /**
     * Rechazar/Eliminar un pago
     * Requiere: pagos.reject ability (solo Sistemas/Administración)
     */
    public function rechazar(Request $request, $id)
    {
        // Validación adicional de permisos
        if (! \App\Support\AreaPermissions::allows($request->user(), 'pagos.reject')) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'No autorizado para rechazar pagos');
        }

        try {
            DB::beginTransaction();
            
            $pago = Pago::findOrFail($id);
            $idEmpresa = $pago->id_empresa;
            $idFeria = $pago->id_feria;
            $estadoPago = $pago->estado;
            $payloadPago = $this->buildPagoBroadcastData($pago);
            
            $extension = strtolower((string) $pago->extension);
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                $nombreArchivo = $pago->id . '.' . $extension;
                File::delete(public_path('img/pagos/' . $nombreArchivo));
                Storage::disk('local')->delete('pagos/' . $nombreArchivo);
            }
            
            // Eliminar registro
            $pago->recibo()->delete();
            $pago->delete();

            // Si el pago estaba aprobado, recalcular estados de contratos
            if ($estadoPago == 1) {
                $this->actualizarEstadoPagoContratos($idEmpresa, $idFeria);
            }

            $this->dispatchBroadcastSafely(new RegistroActualizado(
                $payloadPago,
                'delete',
                (int) $idFeria,
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'El pago ha sido rechazado y eliminado'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar pagos a Excel (compatible con maatwebsite/excel v3.1)
     */
    public function exportar($id_feria)
    {
        try {
            $feria = Feria::findOrFail($id_feria);
            $nombreFeria = str_replace(' ', '_', $feria->nombre_feria);
            
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\PagosFeriaExport($id_feria),
                'Pagos_' . $nombreFeria . '_' . now()->format('Y-m-d') . '.xlsx'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar los pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcular el campo 'pago' de todos los contratos de una feria
     * Requiere: pagos.recalculate ability (solo Sistemas/Administración)
     * Útil para actualizar datos antiguos
     */
    public function recalcularEstadosPago($idFeria)
    {
        // Validación adicional de permisos - obtener usuario desde request
        $user = request()->user();
        if (! \App\Support\AreaPermissions::allows($user, 'pagos.recalculate')) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'No autorizado para recalcular estados de pago');
        }

        try {
            DB::beginTransaction();

            // Obtener todas las empresas que tienen contratos en esta feria
            $empresas = Contrato::where('id_feria', $idFeria)
                ->where('codigo_contrato', '>', 0)
                ->distinct()
                ->pluck('id_empresa');

            $count = 0;
            foreach ($empresas as $idEmpresa) {
                $this->actualizarEstadoPagoContratos($idEmpresa, $idFeria);
                $count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Estados de pago recalculados para {$count} empresas"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular estados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar recibo de pago en PDF (FPDF)
     * Requiere: pagos.view ability
     */
    public function recibo(Request $request, $id)
    {
        if (! \App\Support\AreaPermissions::allows($request->user(), 'pagos.view')) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'No autorizado para ver pagos');
        }

        require_once app_path('Support/fpdf_helper.php');

        $pago = Pago::with([
            'empresa:id_empresa,nombre_empresa,nit',
            'feria:id_feria,nombre_feria,codigo_contrato',
            'usuario:id_usuario,nombre_usuario',
            'usuarioAprobacion:id_usuario,nombre_usuario',
            'recibo:id_recibo,id_pago,numero_recibo,anio,tipo_pago,user_id',
        ])->findOrFail($id);

        $recibo = $pago->recibo;
        if (!$recibo) {
            if ((int) $pago->tipo_pago === 1) {
                $recibo = app(ReciboNumberService::class)
                    ->generarParaPago($pago, (int) ($request->user()?->id_usuario ?? $pago->id_usuario));
            } else {
                abort(404, 'Este pago no tiene recibo generado.');
            }
        }

        $empresa     = $pago->empresa;
        $feria       = $pago->feria;
        $registrador = $pago->usuario?->nombre_usuario ?? 'N/A';
        $aprobador   = $pago->usuarioAprobacion?->nombre_usuario ?? null;
        $monto       = (float) $pago->monto;
        $montoFmt    = number_format($monto, 2, '.', ',');

        // Contratos de la empresa en esta feria
        $contratos   = \App\Models\Contrato::where('id_empresa', $pago->id_empresa)
            ->where('id_feria', $pago->id_feria)
            ->where('codigo_contrato', '>', 0)
            ->select('codigo_contrato')
            ->get();
        $prefijoContrato = trim((string) ($feria?->codigo_contrato ?? ''));
        $codigosContrato = $contratos->map(function ($c) use ($prefijoContrato) {
            return $prefijoContrato . str_pad((string) $c->codigo_contrato, 5, '0', STR_PAD_LEFT);
        })->values()->all();
        $nroRecibo = preg_replace('/^N\s*/i', '', (string) $recibo->numero_recibo);

        $contratoLines = empty($codigosContrato) ? ['—'] : $codigosContrato;
        if (count($contratoLines) > 5) {
            $contratoLines = array_slice($contratoLines, 0, 4);
            $contratoLines[] = '...';
        }

        $contratoLineH = 3.5;
        $contratoValueH = max(6.0, min(24.0, (count($contratoLines) * $contratoLineH) + 1.0));
        $contratoFont = count($contratoLines) >= 4 ? 6.0 : 6.8;

        $enteroMonto = (int) floor($monto);
        $centavosMonto = (int) round(($monto - $enteroMonto) * 100);
        if ($centavosMonto === 100) {
            $enteroMonto += 1;
            $centavosMonto = 0;
        }
        $montoLiteral = mb_strtoupper(
            $this->convertirMontoLiteral($enteroMonto)
            . ' con '
            . str_pad((string) $centavosMonto, 2, '0', STR_PAD_LEFT)
            . '/100 Bolivianos',
            'UTF-8'
        );

        // Fecha descompuesta para "Cochabamba, ___ de ___ de 20___"
        $partes    = $pago->fecha ? explode('-', $pago->fecha) : [date('Y'), date('m'), date('d')];
        $dia       = isset($partes[2]) ? ltrim($partes[2], '0') : date('j');
        $mesesArr  = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $mesNum    = isset($partes[1]) ? (int) $partes[1] : (int) date('m');
        $mesNombre = $mesesArr[$mesNum] ?? '';
        $anio      = isset($partes[0]) ? substr($partes[0], 2) : date('y');

        // ── Página carta: recibo contenido en media hoja ────────────────────
        $pageW = 216;
        $pageH = 279;

        // Marco del recibo con altura dinámica según contenido
        $frameX = 10;
        $frameY = 10;
        $frameW = 196;
        $contenidoInferiorEstimado = max(82.0, 60.0 + $contratoValueH);
        $firmasAreaH = 34.0;
        $frameH = max(108.0, min(128.0, $contenidoInferiorEstimado + $firmasAreaH - 5.0));
        $bord   = 5;

        $pdf = new \FPDF('P', 'mm', 'letter');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();

        // ── Bordes dobles azul marino ────────────────────────────────────────
        $pdf->SetDrawColor(0);
        $pdf->SetLineWidth(1.0);
        $pdf->Rect($frameX, $frameY, $frameW, $frameH);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($frameX + 2, $frameY + 2, $frameW - 4, $frameH - 4);

        // Límites de contenido
        $L  = $frameX + 3;
        $T  = $frameY + 3;
        $R  = $frameX + $frameW - 3;
        $B  = $frameY + $frameH - 3;
        $CW = $R - $L;                // 200

        // Divisor vertical: panel izquierdo 152 mm, panel derecho 48 mm
        $divX   = $L + 152;
        $leftW  = $divX - $L;   // 152
        $rightW = $R - $divX;   // 48

        $hdrH     = 17;
        $hdrLineY = $T + $hdrH;   // 25

        // ── Logo ─────────────────────────────────────────────────────────────
        $logoPath = base_path('resources/images/logo3.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, $L + 1, $T + 1, 12, 0);
        }

        // ── Logo adicional ───────────────────────────────────────────────────
        $logoPath2 = base_path('public/images/logo.png');
        if (file_exists($logoPath2)) {
            $pdf->Image($logoPath2, $L + 14, $T + 1, 12, 0);
        }

        // ── Encabezado izquierdo: nombre organización ─────────────────────────
        $pdf->SetXY($L + 28, $T + 2);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($leftW - 29, 5, utf8_decode('Feria Exposición Internacional de Cochabamba'), 0, 1, 'C');
        $pdf->SetX($L + 28);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($leftW - 29, 5, 'FEXCO', 0, 1, 'C');
        $pdf->SetX($L + 28);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);

        // ── Encabezado derecho: "RECIBO" + número ─────────────────────────────
        $pdf->SetXY($divX + 2, $T + 0.5);
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->SetTextColor(0, 0, 0 );
        $pdf->Cell($rightW - 2, 11, 'RECIBO', 0, 1, 'C');
        $pdf->SetXY($divX + 2, $T + 10.8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($rightW - 2, 4, 'No. ' . $nroRecibo, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        // ── Línea horizontal divisora del encabezado ─────────────────────────
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($L, $hdrLineY, $R, $hdrLineY);

        // ── Panel derecho: cajas N° Ctto. y Bs. ─────────────────────────────
        $boxX  = $divX + 3;
        $boxW  = $rightW - 6;
        $boxY  = $hdrLineY + 3;
        $itemH = 9.5;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);

        // N° Contrato (dinámico: uno debajo de otro según cantidad)
        $pdf->SetXY($boxX, $boxY);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($boxW, 4, utf8_decode('N° Ctto.'), 1, 1, 'C');

        $contratoValueY = $boxY + 4;
        $pdf->Rect($boxX, $contratoValueY, $boxW, $contratoValueH);
        $pdf->SetFont('Arial', '', $contratoFont);
        $pdf->SetTextColor(0, 0, 0);

        $startContratoY = $contratoValueY + (($contratoValueH - (count($contratoLines) * $contratoLineH)) / 2);
        foreach ($contratoLines as $index => $codigoContrato) {
            $pdf->SetXY($boxX, $startContratoY + ($index * $contratoLineH));
            $pdf->Cell($boxW, $contratoLineH, utf8_decode($codigoContrato), 0, 0, 'C');
        }

        // Bs.
        $bsY = $contratoValueY + $contratoValueH + 2;
        $pdf->SetXY($boxX, $bsY);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($boxW, 4, 'Bs.', 1, 1, 'C');
        $pdf->SetX($boxX);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($boxW, $itemH - 4, $montoFmt, 1, 1, 'C');

        // ── Panel izquierdo: campos tipo recibo físico ────────────────────────
        $fX   = $L + 1;
        $fMax = $divX - 2;
        $fW   = $fMax - $fX;   // ~148 mm
        $fh   = 10;
        $fs   = 7.5;
        $fY   = $hdrLineY + 2;

        // Closure: línea de subrayado de campo (gris tenue)
        $ul = function (float $x1, float $x2, float $y) use ($pdf): void {
            $pdf->SetDrawColor(170, 170, 170);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($x1, $y, $x2, $y);
            $pdf->SetDrawColor(0, 0, 0);
        };

        // Closure: fila simple con label + valor + subrayado
        $fila = function (string $label, string $val, float $lblW) use (
            $pdf, $fX, $fMax, $fW, $fh, $fs, &$fY, $ul
        ): void {
            $pdf->SetXY($fX, $fY);
            $pdf->SetFont('Arial', 'B', $fs);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($lblW, $fh, utf8_decode($label), 0, 0, 'L');
            $pdf->SetFont('Arial', '', $fs);
            $pdf->Cell($fW - $lblW, $fh, utf8_decode($val), 0, 1, 'L');
            $ul($fX + $lblW, $fMax, $fY + $fh - 1);
            $fY += $fh;
        };

        // Recibí de
        $fila('Recibí de:', $empresa?->nombre_empresa ?? '', 23);

        // La suma de en bolivianos
        $sufW = 32;
        $pdf->SetXY($fX, $fY);
        $pdf->SetFont('Arial', 'B', $fs);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(23, $fh, utf8_decode('La suma de:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', $fs);
        $pdf->Cell($fW - 23 - $sufW, $fh, utf8_decode($montoLiteral), 0, 0, 'L');
        $pdf->SetFont('Arial', 'I', 6.5);
        $pdf->SetTextColor(90, 90, 90);
        $ul($fX + 23, $fMax - $sufW - 1, $fY + $fh - 1);
        $fY += $fh;

        // Por concepto de
        $fila('Por concepto de:', 'Participación en ' . ($feria?->nombre_feria ?? 'feria'), 31);

        // Forma de pago con checkboxes (segun tipo registrado)
        $esEfectivo = (int) $pago->tipo_pago === 1;
        $esCheque = (int) $pago->tipo_pago === 2;

        $pdf->SetXY($fX, $fY);
        $pdf->SetFont('Arial', 'B', $fs);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(35, $fh, utf8_decode('Forma de pago:'), 0, 0, 'L');

        $boxSize = 3.8;
        $rowY = $fY + 3.0;
        $cursorX = $fX + 35;

        $drawCheck = function (float $x, float $y, bool $checked) use ($pdf, $boxSize): void {
            $pdf->Rect($x, $y, $boxSize, $boxSize);
            if ($checked) {
                $pdf->SetXY($x, $y - 0.2);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->Cell($boxSize, $boxSize + 0.3, 'X', 0, 0, 'C');
            }
        };

        $drawCheck($cursorX, $rowY, $esEfectivo);
        $pdf->SetXY($cursorX + $boxSize + 1.2, $fY);
        $pdf->SetFont('Arial', '', $fs);
        $pdf->Cell(24, $fh, utf8_decode('En efectivo'), 0, 0, 'L');

        $cursorX += 27;
        $drawCheck($cursorX, $rowY, $esCheque);
        $pdf->SetXY($cursorX + $boxSize + 1.2, $fY);
        $pdf->Cell(17, $fh, utf8_decode('Cheque'), 0, 1, 'L');

        $fY += $fh;

        // Fecha en una sola línea continua
        $fechaTexto = 'Cochabamba, ' . $dia . ' de ' . $mesNombre . ' de 20' . $anio;
        $pdf->SetXY($fX, $fY);
        $pdf->SetFont('Arial', 'B', $fs);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($fW, $fh, utf8_decode($fechaTexto), 0, 1, 'L');
        $fY += $fh;

        // ── Divisor vertical entre paneles (tras calcular fY) ────────────────
        $sigY = max($fY + 2, $B - $firmasAreaH);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($divX, $hdrLineY, $divX, $sigY - 1);

        // ── Área de firmas ────────────────────────────────────────────────────
        $halfW = $CW / 2;
        $midX  = $L + $halfW;

        $pdf->SetLineWidth(0.4);
        $pdf->Line($L, $sigY, $R, $sigY);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($midX, $sigY, $midX, $B);

        // Etiquetas (separadas de la línea superior y del divisor vertical)
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($L + 3, $sigY + 6);
        $pdf->Cell($halfW - 6, 5, utf8_decode('RECIBI CONFORME:'), 0, 1, 'L');
        $pdf->SetXY($midX + 6, $sigY + 6);
        $pdf->Cell($halfW - 9, 5, utf8_decode('ENTREGUE CONFORME:'), 0, 1, 'L');

        // Líneas de firma
        $sigLineY = $B - 12;
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($L + 10, $sigLineY, $midX - 10, $sigLineY);
        $pdf->Line($midX + 10, $sigLineY, $R - 10, $sigLineY);

        // Nombres debajo de las líneas
        $pdf->SetXY($L + 3, $sigLineY + 1);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($halfW - 6, 4, utf8_decode($registrador), 0, 0, 'C');
        $pdf->Cell($halfW - 6, 4, '', 0, 1, 'C');

        $nombreArchivo = 'Recibo_Pago_' . str_pad($pago->id, 6, '0', STR_PAD_LEFT) . '.pdf';
        $pdf->Output('I', $nombreArchivo);
        exit;
    }

    private function convertirMontoLiteral(int $monto): string
    {
        if ($monto === 0) {
            return 'cero';
        }

        return trim($this->convertirNumeroTexto($monto));
    }

    private function basicoNumeroTexto(int $numero): string
    {
        $valor = [
            'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciseis', 'diecisiete',
            'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidos', 'veintitres',
            'veinticuatro', 'veinticinco', 'veintiseis', 'veintisiete', 'veintiocho', 'veintinueve'
        ];

        return $valor[$numero - 1] ?? '';
    }

    private function decenasNumeroTexto(int $n): string
    {
        $decenas = [
            30 => 'treinta', 40 => 'cuarenta', 50 => 'cincuenta', 60 => 'sesenta',
            70 => 'setenta', 80 => 'ochenta', 90 => 'noventa'
        ];

        if ($n <= 29) {
            return $this->basicoNumeroTexto($n);
        }

        $x = $n % 10;
        if ($x === 0) {
            return $decenas[$n] ?? '';
        }

        return ($decenas[$n - $x] ?? '') . ' y ' . $this->basicoNumeroTexto($x);
    }

    private function centenasNumeroTexto(int $n): string
    {
        $cientos = [
            100 => 'cien', 200 => 'doscientos', 300 => 'trescientos',
            400 => 'cuatrocientos', 500 => 'quinientos', 600 => 'seiscientos',
            700 => 'setecientos', 800 => 'ochocientos', 900 => 'novecientos'
        ];

        if ($n >= 100) {
            if ($n % 100 === 0) {
                return $cientos[$n] ?? '';
            }

            $u = (int) substr((string) $n, 0, 1);
            $d = (int) substr((string) $n, 1, 2);

            return ($u === 1 ? 'ciento' : ($cientos[$u * 100] ?? '')) . ' ' . $this->decenasNumeroTexto($d);
        }

        return $this->decenasNumeroTexto($n);
    }

    private function milesNumeroTexto(int $n): string
    {
        if ($n > 999) {
            if ($n === 1000) {
                return 'mil';
            }

            $l = strlen((string) $n);
            $c = (int) substr((string) $n, 0, $l - 3);
            $x = (int) substr((string) $n, -3);

            if ($c === 1) {
                return 'mil ' . $this->centenasNumeroTexto($x);
            }

            if ($x !== 0) {
                return $this->centenasNumeroTexto($c) . ' mil ' . $this->centenasNumeroTexto($x);
            }

            return $this->centenasNumeroTexto($c) . ' mil';
        }

        return $this->centenasNumeroTexto($n);
    }

    private function millonesNumeroTexto(int $n): string
    {
        if ($n === 1000000) {
            return 'un millon';
        }

        $l = strlen((string) $n);
        $c = (int) substr((string) $n, 0, $l - 6);
        $x = (int) substr((string) $n, -6);
        $cadena = $c === 1 ? ' millon ' : ' millones ';

        return $this->milesNumeroTexto($c) . $cadena . ($x > 0 ? $this->milesNumeroTexto($x) : '');
    }

    private function convertirNumeroTexto(int $n): string
    {
        if ($n >= 1 && $n <= 29) {
            return $this->basicoNumeroTexto($n);
        }

        if ($n >= 30 && $n < 100) {
            return $this->decenasNumeroTexto($n);
        }

        if ($n >= 100 && $n < 1000) {
            return $this->centenasNumeroTexto($n);
        }

        if ($n >= 1000 && $n <= 999999) {
            return $this->milesNumeroTexto($n);
        }

        if ($n >= 1000000) {
            return $this->millonesNumeroTexto($n);
        }

        return '';
    }

    /**
     * Normalizar valor de monto (convertir a número)
     */
    private function normalizarMonto($monto)
    {
        if (is_numeric($monto)) {
            return (float)$monto;
        }
        // Si viene en formato "Bs. 100.00" o similar
        return (float) preg_replace('/[^0-9.]/', '', $monto);
    }

    /**
     * Reglas de negocio para emisión de recibo al registrar pago.
     */
    private function debeGenerarRecibo(int $tipoPago, bool $generarReciboSolicitado): bool
    {
        // Efectivo: siempre emite recibo.
        if ($tipoPago === 1) {
            return true;
        }

        // Cheque: solo emite si el usuario marcó la opción.
        if ($tipoPago === 2) {
            return $generarReciboSolicitado;
        }

        // Otros tipos de pago no generan recibo por defecto.
        return false;
    }

    private function obtenerNombreTipoPago(int $tipoPago): string
    {
        return match ($tipoPago) {
            0 => 'Depósito',
            1 => 'Efectivo',
            2 => 'Cheque',
            default => 'No especificado',
        };
    }

    /**
     * Datos mínimos para broadcasting de pagos.
     */
    private function buildPagoBroadcastData(Pago $pago): array
    {
        return [
            'id' => (int) $pago->id,
            'id_empresa' => (int) $pago->id_empresa,
            'id_feria' => (int) $pago->id_feria,
            'estado' => (int) ($pago->estado ?? 0),
        ];
    }

    /**
     * Crear notificaciones persistentes para usuarios de administración
     * cuando se registra un nuevo pago y emitir evento en tiempo real.
     */
    private function notificarAdministracionPagoCreado(Pago $pago): void
    {
        try {
            $pago->loadMissing([
                'empresa:id_empresa,nombre_empresa',
                'feria:id_feria,nombre_feria',
                'usuario:id_usuario,nombre_usuario,nick_usuario',
            ]);

            $empresa = $pago->empresa->nombre_empresa ?? 'Empresa';
            $feria = $pago->feria->nombre_feria ?? 'Feria';
            $registradoPor = $pago->usuario->nombre_usuario ?? $pago->usuario->nick_usuario ?? 'Usuario';

            $titulo = 'Nuevo pago registrado';
            $mensaje = "{$empresa} registró un pago en {$feria}";

            $payload = [
                'id_pago' => (int) $pago->id,
                'id_empresa' => (int) $pago->id_empresa,
                'id_feria' => (int) $pago->id_feria,
                'empresa' => $empresa,
                'feria' => $feria,
                'monto' => (float) $this->normalizarMonto($pago->monto),
                'tipo_pago' => (int) ($pago->tipo_pago ?? 0),
                'registrado_por' => $registradoPor,
                'fecha' => now()->toDateTimeString(),
            ];

            $idUsuarioCreador = (int) $pago->id_usuario;

            $adminUsers = Usuario::query()->get()->filter(static function ($user) use ($idUsuarioCreador) {
                return AreaPermissions::isAdministrativeArea($user)
                    && (int) $user->id_usuario !== $idUsuarioCreador;
            });

            foreach ($adminUsers as $adminUser) {
                NotificacionAdmin::create([
                    'id_usuario' => (int) $adminUser->id_usuario,
                    'tipo' => 'pago_creado',
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'payload' => $payload,
                    'leida' => false,
                ]);
            }

            $this->dispatchBroadcastSafely(new NotificacionAdminCreada([
                'tipo' => 'pago_creado',
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'payload' => $payload,
                'created_at' => now()->toDateTimeString(),
            ]));
        } catch (\Throwable $e) {
            // No interrumpir el flujo de registro de pago por fallos de notificación.
        }
    }

    /**
     * Evitar que una caída de Reverb/Pusher rompa operaciones críticas.
     */
    private function dispatchBroadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

