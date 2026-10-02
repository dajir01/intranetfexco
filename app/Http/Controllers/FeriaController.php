<?php

namespace App\Http\Controllers;

use App\Models\Feria;
use App\Models\Modelo_Contrato;
use App\Models\Pabellon;
use App\Events\FeriaActualizada;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeriaController extends Controller
{
    /**
     * Obtener el modelo de contrato activo
     */
    public function modeloContratoActivo()
    {
        $modelo = Modelo_Contrato::query()
            ->where('estado', 1)
            ->orderByDesc('id_modelo_contrato')
            ->first();

        if (!$modelo) {
            return response()->json([
                'success' => false,
                'message' => 'No existe un modelo de contrato activo para asignar a la feria.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id_modelo_contrato' => $modelo->id_modelo_contrato,
                'nombre' => $modelo->nombre,
                'estado' => (int) $modelo->estado,
                'fecha_creacion' => $modelo->fecha,
            ],
        ]);
    }

    /**
     * Obtener todos los modelos de contrato disponibles, filtrados por tipo
     * 
     * Query parameter: tipo (1=Contrato, 2=Adenda, por defecto 1)
     */
    public function modelosContrato(Request $request)
    {
        $tipo = (int) ($request->query('tipo', 1));

        // Validar que el tipo sea válido (1 o 2)
        if (!in_array($tipo, [1, 2])) {
            $tipo = 1; // Por defecto Contrato
        }

        $modelos = Modelo_Contrato::query()
            ->where('tipo', $tipo)
            ->select('id_modelo_contrato', 'nombre', 'estado', 'tipo')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modelos->map(fn($m) => [
                'id_modelo_contrato' => $m->id_modelo_contrato,
                'nombre' => $m->nombre,
                'estado' => (int) $m->estado,
                'tipo' => (int) $m->tipo,
            ]),
        ]);
    }

    /**
     * Obtener listado de ferias con sus pabellones asociados
     */
    public function index(Request $request)
    {
        try {
            // Obtener todas las ferias con sus pabellones, ordenadas por fecha de inicio descendente
            $ferias = Feria::with('pabellones')
                ->orderByFechaEvento()
                ->get();

            // Mapear ferias con sus pabellones concatenados
            $feriasConPabellones = $ferias->map(function ($feria) {
                // Obtener nombres de pabellones y concatenarlos
                $nombresPabellones = $feria->pabellones->pluck('nombre_pabellon')->toArray();

                return [
                    'id_feria' => $feria->id_feria,
                    'nombre_feria' => $feria->nombre_feria,
                    'fecha_inicio' => $feria->fecha_inicio,
                    'fecha_fin' => $feria->fecha_fin,
                    'estado_feria' => $feria->estado_feria,
                    'pabellones' => !empty($nombresPabellones) ? implode(', ', $nombresPabellones) : 'Sin pabellones',
                    'pabellones_array' => $nombresPabellones, // Para uso opcional en frontend
                    'total_pabellones' => count($nombresPabellones),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $feriasConPabellones,
                'meta' => [
                    'total' => $feriasConPabellones->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ferias',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar una nueva feria
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_feria' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'puertas_acceso' => ['nullable', 'string', 'max:255'],
            'codigo_contrato' => ['nullable', 'string', 'max:255'],
            'codigo_factura' => ['nullable', 'string', 'max:255'],
            'inicio' => ['nullable', 'string', 'max:255'],
            'informacion' => ['nullable', 'string', 'max:100'],
            'tipo_cred' => ['nullable', 'array'],
            'tipo_cred.*' => ['string', Rule::in(['Expositor', 'Prensa', 'Oficial', 'Servicios', 'Negocios'])],
            'id_modelocontrato' => ['required', 'integer', 'exists:modelo_contrato,id_modelo_contrato'],
            'id_modeloademda' => ['nullable', 'integer', 'exists:modelo_contrato,id_modelo_contrato'],
        ], [
            'nombre_feria.required' => 'El nombre de la feria es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser menor a la fecha de inicio.',
            'inicio.max' => 'El inicio no puede exceder 255 caracteres.',
            'informacion.max' => 'La información no puede exceder 100 caracteres.',
            'tipo_cred.array' => 'Los tipos de credenciales deben ser un listado.',
            'tipo_cred.*.in' => 'Tipo de credencial inválido.',
            'id_modelocontrato.required' => 'El modelo de contrato es obligatorio.',
            'id_modelocontrato.integer' => 'El modelo de contrato debe ser un número.',
            'id_modelocontrato.exists' => 'El modelo de contrato seleccionado no existe.',
            'id_modeloademda.integer' => 'El modelo de adenda debe ser un número.',
            'id_modeloademda.exists' => 'El modelo de adenda seleccionado no existe.',
        ]);

        // Lógica legacy: construir cred_inicio y tipo_credenciales concatenado
        $mapeo = [
            'Expositor' => ['prefijo' => 1500, 'final' => 26800],
            'Prensa'    => ['prefijo' => 150,  'final' => 28300],
            'Oficial'   => ['prefijo' => 200,  'final' => 28450],
            'Servicios' => ['prefijo' => 100,  'final' => 28650],
            'Negocios'  => ['prefijo' => 500,  'final' => 28750],
        ];

        $tipos = $validated['tipo_cred'] ?? [];
        $credInicio = '';
        $tiposConcat = '';

        foreach ($tipos as $tipo) {
            $letra = mb_substr($tipo, 0, 1);
            $prefijo = $mapeo[$tipo]['prefijo'] ?? '';
            $codigoFinal = $mapeo[$tipo]['final'] ?? '';
            // Formato: prefijo ; letra ; nombre_tipo ; codigo_final ;
            if ($prefijo !== '' && $codigoFinal !== '') {
                $credInicio .= $prefijo.';'.$letra.';'.$tipo.';'.$codigoFinal.';';
            }
            // Concatenación de tipos separada por ;
            $tiposConcat .= $tipo.';';
        }

        $feria = Feria::create([
            'nombre_feria' => $validated['nombre_feria'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'estado_feria' => 0, // por defecto 0
            'puertas_acceso' => $validated['puertas_acceso'] ?? null,
            'codigo_contrato' => $validated['codigo_contrato'] ?? null,
            'codigo_factura' => $validated['codigo_factura'] ?? null,
            'inicio' => $validated['inicio'] ?? null,
            'cred_inicio' => $credInicio,
            'info' => $validated['informacion'] ?? null,
            'tipo_credenciales' => $tiposConcat !== '' ? $tiposConcat : null,
            'id_modelocontrato' => $validated['id_modelocontrato'],
            'id_modeloademda' => $validated['id_modeloademda'] ?? null,
        ]);
        
        // Broadcasting en tiempo real
        FeriaActualizada::dispatch($feria->id_feria, 'create', ['nombre_feria' => $feria->nombre_feria]);

        return response()->json([
            'success' => true,
            'message' => 'Feria registrada correctamente.',
            'data' => $feria,
        ], 201);
    }

    /**
     * Obtener una feria específica con sus pabellones
     */
    public function show($id)
    {
        try {
            $feria = Feria::with('pabellones')->findOrFail($id);
            
            // Modelo Contrato asignado
            $modeloContratoAsignado = null;
            if (!empty($feria->id_modelocontrato)) {
                $modeloContratoAsignado = Modelo_Contrato::query()
                    ->where('id_modelo_contrato', $feria->id_modelocontrato)
                    ->first();
            }

            // Modelo Adenda asignado
            $modeloAdendaAsignado = null;
            if (!empty($feria->id_modeloademda)) {
                $modeloAdendaAsignado = Modelo_Contrato::query()
                    ->where('id_modelo_contrato', $feria->id_modeloademda)
                    ->first();
            }

            $modeloActivo = Modelo_Contrato::query()
                ->where('estado', 1)
                ->orderByDesc('id_modelo_contrato')
                ->first();

            // Parsear tipos de credenciales a arreglo para facilitar el front
            $tiposArray = array_filter(explode(';', $feria->tipo_credenciales ?? ''), 'strlen');

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => array_merge($feria->toArray(), [
                        'id_modelocontrato' => (int)$feria->id_modelocontrato ?? null,
                        'id_modeloademda' => (int)$feria->id_modeloademda ?? null,
                    ]),
                    'tipo_cred' => $tiposArray,
                    'pabellones' => $feria->pabellones,
                    'modelo_contrato_asignado' => $modeloContratoAsignado
                        ? [
                            'id_modelo_contrato' => $modeloContratoAsignado->id_modelo_contrato,
                            'nombre' => $modeloContratoAsignado->nombre,
                            'estado' => (int) $modeloContratoAsignado->estado,
                          ]
                        : null,
                    'modelo_adenda_asignado' => $modeloAdendaAsignado
                        ? [
                            'id_modelo_contrato' => $modeloAdendaAsignado->id_modelo_contrato,
                            'nombre' => $modeloAdendaAsignado->nombre,
                            'estado' => (int) $modeloAdendaAsignado->estado,
                          ]
                        : null,
                    'modelo_contrato_activo' => $modeloActivo
                        ? [
                            'id_modelo_contrato' => $modeloActivo->id_modelo_contrato,
                            'nombre' => $modeloActivo->nombre,
                            'estado' => (int) $modeloActivo->estado,
                          ]
                        : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feria no encontrada',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Actualizar una feria existente
     */
    public function update(Request $request, $id)
    {
        $feria = Feria::findOrFail($id);

        $validated = $request->validate([
            'nombre_feria' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'puertas_acceso' => ['nullable', 'string', 'max:255'],
            'id_modelocontrato' => ['required', 'integer', 'exists:modelo_contrato,id_modelo_contrato'],
            'id_modeloademda' => ['nullable', 'integer', 'exists:modelo_contrato,id_modelo_contrato'],
            'codigo_contrato' => ['nullable', 'string', 'max:255'],
            'codigo_factura' => ['nullable', 'string', 'max:255'],
            'inicio' => ['nullable', 'string', 'max:255'],
            'informacion' => ['nullable', 'string', 'max:100'],
            'estado_feria' => ['nullable', Rule::in([0, 1])],
            'tipo_cred' => ['nullable', 'array'],
            'tipo_cred.*' => ['string', Rule::in(['Expositor', 'Prensa', 'Oficial', 'Servicios', 'Negocios'])],
        ]);

        // Lógica legacy de credenciales
        $mapeo = [
            'Expositor' => ['prefijo' => 1500, 'final' => 26800],
            'Prensa'    => ['prefijo' => 150,  'final' => 28300],
            'Oficial'   => ['prefijo' => 200,  'final' => 28450],
            'Servicios' => ['prefijo' => 100,  'final' => 28650],
            'Negocios'  => ['prefijo' => 500,  'final' => 28750],
        ];

        $tipos = $validated['tipo_cred'] ?? [];
        $credInicio = '';
        $tiposConcat = '';

        foreach ($tipos as $tipo) {
            $letra = mb_substr($tipo, 0, 1);
            $prefijo = $mapeo[$tipo]['prefijo'] ?? '';
            $codigoFinal = $mapeo[$tipo]['final'] ?? '';
            if ($prefijo !== '' && $codigoFinal !== '') {
                $credInicio .= $prefijo.';'.$letra.';'.$tipo.';'.$codigoFinal.';';
            }
            $tiposConcat .= $tipo.';';
        }

        $payload = [
            'nombre_feria' => $validated['nombre_feria'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'estado_feria' => isset($validated['estado_feria']) ? (int) $validated['estado_feria'] : ($feria->estado_feria ?? 0),
            'puertas_acceso' => $validated['puertas_acceso'] ?? null,
            'codigo_contrato' => $validated['codigo_contrato'] ?? null,
            'codigo_factura' => $validated['codigo_factura'] ?? null,
            'inicio' => $validated['inicio'] ?? null,
            'cred_inicio' => $credInicio,
            'info' => $validated['informacion'] ?? null,
            'tipo_credenciales' => $tiposConcat !== '' ? $tiposConcat : null,
        ];

        $feria->id_modelocontrato = $validated['id_modelocontrato'];
        $feria->id_modeloademda = $validated['id_modeloademda'] ?? null;

        $feria->update($payload);
        
        // Broadcasting en tiempo real
        FeriaActualizada::dispatch($feria->id_feria, 'update', ['nombre_feria' => $feria->nombre_feria]);

        return response()->json([
            'success' => true,
            'message' => 'Feria actualizada correctamente.',
            'data' => $feria->fresh(),
            'tipo_cred' => array_filter(explode(';', $tiposConcat), 'strlen'),
        ]);
    }

    /**
     * Activar una feria (estado_feria = 1).
     */
    public function activate($id)
    {
        try {
            $feria = null;
            DB::transaction(function () use ($id, &$feria) {
                $feria = Feria::lockForUpdate()->findOrFail($id);

                if ((int) $feria->estado_feria === 1) {
                    throw new \RuntimeException('La feria ya está activa.');
                }

                $feria->estado_feria = 1;
                $feria->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Feria activada correctamente.',
                'data' => $feria,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feria no encontrada',
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo activar la feria',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desactivar una feria (estado_feria = 0).
     */
    public function deactivate($id)
    {
        try {
            $feria = null;
            DB::transaction(function () use ($id, &$feria) {
                $feria = Feria::lockForUpdate()->findOrFail($id);

                if ((int) $feria->estado_feria === 0) {
                    throw new \RuntimeException('La feria ya está inactiva.');
                }

                $feria->estado_feria = 0;
                $feria->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Feria desactivada correctamente.',
                'data' => $feria,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Feria no encontrada',
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo desactivar la feria',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
