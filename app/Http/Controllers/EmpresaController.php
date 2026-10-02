<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Backedempresa;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Pais;
use App\Models\Localidad;
use App\Models\Rubro;
use App\Models\Subrubro;
use App\Models\Stand;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmpresaController extends Controller
{
    /**
     * Listado paginado de empresas con búsqueda y filtros.
     */
    public function index(Request $request)
    {
        try {
            $perPage  = max(1, min(100, (int) $request->get('per_page', 15)));
            $page     = max(1, (int) $request->get('page', 1));
            $search   = trim($request->get('q', ''));
            $sortKey  = $request->get('sort_by', 'nombre_empresa');
            $sortDir  = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
            $estado   = $request->get('estado'); // null | '1' | '0'

            $allowed = ['nombre_empresa', 'nit', 'nombre_gerente', 'email', 'telefono', 'id_empresa'];
            if (! in_array($sortKey, $allowed, true)) {
                $sortKey = 'nombre_empresa';
            }

            $query = Empresa::query()
                ->select([
                    'id_empresa',
                    'nombre_empresa',
                    'nit',
                    'nombre_gerente',
                    'telefono',
                    'email',
                    'ciudad',
                    'pais',
                    'es_activo',
                    'created_at',
                    'updated_at',
                    'id_usuario_creacion',
                    'id_usuario_actualizacion',
                ]);

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre_empresa', 'like', "%{$search}%")
                      ->orWhere('nit', 'like', "%{$search}%")
                      ->orWhere('nombre_gerente', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telefono', 'like', "%{$search}%");
                });
            }

            if ($estado !== null && $estado !== '') {
                $query->where('es_activo', (bool) $estado);
            }

            $result = $query->orderBy($sortKey, $sortDir)->paginate($perPage, ['*'], 'page', $page);

            $items = $result->items();

            // Resolver nombres de usuarios en lote
            $userIds = collect($items)
                ->flatMap(fn($e) => [$e->id_usuario_creacion, $e->id_usuario_actualizacion])
                ->filter()
                ->unique()
                ->values();

            $usuarios = Usuario::whereIn('id_usuario', $userIds)
                ->pluck('nombre_usuario', 'id_usuario');

            $data = collect($items)->map(function ($e) use ($usuarios) {
                $arr = $e->toArray();
                $arr['usuario_creacion_nombre']     = $e->id_usuario_creacion     ? ($usuarios[$e->id_usuario_creacion]     ?? null) : null;
                $arr['usuario_actualizacion_nombre'] = $e->id_usuario_actualizacion ? ($usuarios[$e->id_usuario_actualizacion] ?? null) : null;
                return $arr;
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'total'        => $result->total(),
                    'per_page'     => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('EmpresaController@index: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al obtener empresas'], 500);
        }
    }

    /**
     * Detalle completo de una empresa (solo lectura).
     */
    public function show($id)
    {
        try {
            $empresa = Empresa::findOrFail($id);

            $data = $empresa->toArray();

            // Resolver nombre de país
            if (!empty($empresa->pais)) {
                $pais = Pais::find($empresa->pais);
                $data['pais_nombre'] = $pais ? $pais->nombre : null;
            } else {
                $data['pais_nombre'] = null;
            }

            // Resolver nombre de ciudad
            if (!empty($empresa->ciudad)) {
                $ciudad = Localidad::find($empresa->ciudad);
                $data['ciudad_nombre'] = $ciudad ? $ciudad->nombre : null;
            } else {
                $data['ciudad_nombre'] = null;
            }

            // Resolver nombre de rubro
            if ($empresa->rubro !== null && $empresa->rubro !== '' && $empresa->rubro != 0) {
                $rubro = Rubro::find($empresa->rubro);
                $data['rubro_nombre'] = $rubro ? $rubro->nombre_rubro : null;
            } else {
                $data['rubro_nombre'] = null;
            }

            // Resolver nombre de subrubro
            if ($empresa->subrubro !== null && $empresa->subrubro !== '' && $empresa->subrubro != 0) {
                $subrubro = Subrubro::find($empresa->subrubro);
                $data['subrubro_nombre'] = $subrubro ? $subrubro->nombre_subrubro : null;
            } else {
                $data['subrubro_nombre'] = null;
            }

            // Resolver categoría desde campo cluster
            $categoriaMap = ['1' => 'Grande', '2' => 'Mediana', '3' => 'Pequeña', '4' => 'Artesano'];
            $data['categoria_nombre'] = $categoriaMap[(string) $empresa->cluster] ?? null;

            // Resolver actividad principal desde id_tipo_representante
            $actividadMap = ['1' => 'Industria Manufacturera', '2' => 'Comercio', '3' => 'Servicios'];
            if (!empty($empresa->id_tipo_representante)) {
                $code = explode('00', (string) $empresa->id_tipo_representante)[0];
                $data['actividad_principal_nombre'] = $actividadMap[$code] ?? null;
            } else {
                $data['actividad_principal_nombre'] = null;
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Empresa no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('EmpresaController@show: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al obtener la empresa'], 500);
        }
    }

    /**
     * Historial de participación en ferias (contratos de la empresa).
     */
    public function ferias($id)
    {
        try {
            $empresa = Empresa::findOrFail($id);

            $contratos = Contrato::with('feria')
                ->where('id_empresa', $empresa->id_empresa)
                ->select([
                    'id_contrato',
                    'id_feria',
                    'codigo_contrato',
                    'total_stands',
                    'stand_1','stand_2','stand_3','stand_4','stand_5',
                    'stand_6','stand_7','stand_8','stand_9','stand_10',
                    'stand_11','stand_12',
                    'precio_total',
                    'descuento',
                    'pago',
                    'entrega',
                    'habilitado_feria',
                    'fecha_realizacion',
                    'estado_reserva',
                    'tipo_expositor',
                ])
                ->orderByDesc('id_contrato')
                ->get();

            // Resumen por feria para estado real de pago (misma lógica que módulo Pagos)
            $contratosPorFeria = $contratos
                ->groupBy('id_feria')
                ->map(function ($items) {
                    $totalContratado = 0.0;

                    foreach ($items as $contrato) {
                        if ((int) ($contrato->codigo_contrato ?? 0) <= 0 || (int) ($contrato->stand_1 ?? 0) <= 0) {
                            continue;
                        }

                        $precioTotal = (float) ($contrato->precio_total ?? 0);
                        $descuento = (float) ($contrato->descuento ?? 0);

                        if ($descuento > 0) {
                            $precioTotal = $precioTotal - ($precioTotal * ($descuento / 100));
                        }

                        $totalContratado += $precioTotal;
                    }

                    return [
                        'total_contratado' => $totalContratado,
                    ];
                });

            $pagosAprobadosPorFeria = Pago::query()
                ->select(['id_feria', 'monto'])
                ->where('id_empresa', $empresa->id_empresa)
                ->where('estado', 1)
                ->get()
                ->groupBy('id_feria')
                ->map(function ($pagos) {
                    return $pagos->sum(function ($pago) {
                        return (float) str_replace(['Bs. ', ',', ' '], '', $pago->monto ?? 0);
                    });
                });

            $standIds = $contratos
                ->flatMap(function ($c) {
                    $ids = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $col = "stand_{$i}";
                        if (! empty($c->$col) && is_numeric($c->$col)) {
                            $ids[] = (int) $c->$col;
                        }
                    }

                    return $ids;
                })
                ->unique()
                ->values();

            $standsCatalogo = Stand::query()
                ->select(['id_stand', 'id_pabellon', 'numero_stand'])
                ->with(['pabellon:id_pabellon,nombre_pabellon'])
                ->whereIn('id_stand', $standIds)
                ->get()
                ->keyBy('id_stand');

            $data = $contratos
                ->groupBy('id_feria')
                ->map(function ($contratosFeria, $idFeria) use ($standsCatalogo, $contratosPorFeria, $pagosAprobadosPorFeria) {
                    $primerContrato = $contratosFeria->first();
                    $prefijoFeria = (string) ($primerContrato->feria?->codigo_contrato ?? '');

                    $resumenFeria = $contratosPorFeria->get($idFeria, ['total_contratado' => 0.0]);
                    $totalContratadoFeria = (float) ($resumenFeria['total_contratado'] ?? 0.0);
                    $totalPagadoFeria = (float) ($pagosAprobadosPorFeria->get($idFeria, 0.0) ?? 0.0);
                    $saldoFeria = $totalContratadoFeria - $totalPagadoFeria;

                    $estadoPagoTexto = 'pendiente';
                    $estadoPagoCodigo = 0;
                    if ($saldoFeria <= 0 && $totalContratadoFeria > 0) {
                        $estadoPagoTexto = 'pagado';
                        $estadoPagoCodigo = 1;
                    } elseif ($saldoFeria < $totalContratadoFeria && $totalPagadoFeria > 0) {
                        $estadoPagoTexto = 'parcial';
                        $estadoPagoCodigo = 2;
                    }

                    $contratosFormateados = $contratosFeria->map(function ($c) use ($prefijoFeria) {
                        $codigoContratoNumerico = (int) ($c->codigo_contrato ?? 0);
                        $numeroContrato = $prefijoFeria . str_pad((string) $codigoContratoNumerico, 5, '0', STR_PAD_LEFT);
                        $esAnulado = ((int) ($c->total_stands ?? 0)) === 0;

                        return [
                            'id_contrato' => $c->id_contrato,
                            'codigo_contrato' => $c->codigo_contrato,
                            'contrato' => [
                                'numero' => $numeroContrato,
                                'codigo' => $codigoContratoNumerico,
                            ],
                            'anulado' => $esAnulado,
                            'fecha_contrato' => $c->fecha_realizacion,
                            'habilitado' => (bool) $c->habilitado_feria,
                            'precio_total' => (float) ($c->precio_total ?? 0),
                        ];
                    })->values();

                    $stands = $contratosFeria
                        ->flatMap(function ($c) use ($standsCatalogo) {
                            $fila = [];
                            for ($i = 1; $i <= 12; $i++) {
                                $col = "stand_{$i}";
                                if (empty($c->$col)) {
                                    continue;
                                }

                                $standId = is_numeric($c->$col) ? (int) $c->$col : null;
                                $standDB = $standId ? $standsCatalogo->get($standId) : null;

                                if ($standDB) {
                                    $nombrePabellon = $standDB->pabellon?->nombre_pabellon ?? 'Sin pabellón';
                                    $numeroStand = $standDB->numero_stand ?? $standDB->id_stand;
                                    $fila[] = [
                                        'id_stand' => $standDB->id_stand,
                                        'numero_stand' => $standDB->numero_stand,
                                        'pabellon' => $nombrePabellon,
                                        'label' => "{$nombrePabellon} - Stand {$numeroStand}",
                                    ];
                                } else {
                                    $fila[] = [
                                        'id_stand' => $standId,
                                        'numero_stand' => null,
                                        'pabellon' => null,
                                        'label' => "Stand {$c->$col}",
                                    ];
                                }
                            }

                            return $fila;
                        })
                        ->unique('label')
                        ->values();

                    return [
                        'id_feria' => (int) $idFeria,
                        'feria' => $primerContrato->feria?->nombre_feria ?? '—',
                        'fecha_feria' => $primerContrato->feria?->fecha_inicio ?? $primerContrato->feria?->inicio ?? null,
                        'contratos' => $contratosFormateados,
                        'stands' => $stands,
                        'precio_total' => round($totalContratadoFeria, 2),
                        'pago' => $estadoPagoCodigo,
                        'pago_estado' => $estadoPagoTexto,
                        'pago_total_aprobado_feria' => round($totalPagadoFeria, 2),
                        'saldo_feria' => round($saldoFeria, 2),
                        'habilitado' => $contratosFormateados->contains(fn ($item) => $item['habilitado'] === true),
                        'fecha_contrato' => $contratosFeria->max('fecha_realizacion'),
                    ];
                })
                ->sortByDesc('fecha_contrato')
                ->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Empresa no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('EmpresaController@ferias: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al obtener participación en ferias'], 500);
        }
    }

    /**
     * Historial de cambios desde backupempresa.
     */
    public function historial($id)
    {
        try {
            $empresa = Empresa::findOrFail($id);

            $registros = Backedempresa::where('id_empresa', $empresa->id_empresa)
                ->orderByDesc('fecha')
                ->get();

            // Resolver IDs de usuarios en una sola consulta; respaldos antiguos pueden guardar el nombre directamente.
            $userIds = $registros->pluck('modificado_por')
                ->filter(fn ($id) => is_numeric($id))
                ->unique()
                ->values();
            $usuarios = \App\Models\Usuario::whereIn('id_usuario', $userIds)
                ->pluck('nombre_usuario', 'id_usuario');

            $data = $registros->map(function ($r) use ($usuarios) {
                $antes   = is_array($r->datos_antes)   ? $r->datos_antes   : (json_decode($r->datos_antes,   true) ?? []);
                $despues = is_array($r->datos_despues)  ? $r->datos_despues  : (json_decode($r->datos_despues, true) ?? []);

                // Detectar campos que cambiaron
                $cambios = [];
                $allKeys = array_unique(array_merge(array_keys($antes), array_keys($despues)));
                foreach ($allKeys as $campo) {
                    $v_antes   = $antes[$campo]   ?? null;
                    $v_despues = $despues[$campo] ?? null;
                    if (!Empresa::valoresEquivalentes($v_antes, $v_despues)) {
                        $cambios[] = [
                            'campo'         => $campo,
                            'valor_anterior' => $v_antes,
                            'valor_nuevo'    => $v_despues,
                        ];
                    }
                }

                return [
                    'id'           => $r->id_backedempresa,
                    'fecha'        => $r->fecha,
                    'usuario'      => is_numeric($r->modificado_por)
                        ? ($usuarios[$r->modificado_por] ?? ('ID ' . $r->modificado_por))
                        : ($r->modificado_por ?: 'Sistema'),
                    'cambios'      => $cambios,
                ];
            })
                ->filter(fn ($registro) => count($registro['cambios']) > 0)
                ->values();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Empresa no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('EmpresaController@historial: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al obtener historial de cambios'], 500);
        }
    }
}
