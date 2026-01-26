<?php

namespace App\Http\Controllers;

use App\Models\Stand;
use App\Models\Pabellon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StandController extends Controller
{
    /**
     * Obtener todos los stands de un pabellón específico
     */
    public function index($pabellonId)
    {
        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            
            $stands = Stand::where('id_pabellon', $pabellonId)
                ->where('feria', $pabellon->feria)
                ->orderByRaw('CAST(numero_stand AS UNSIGNED) ASC')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pabellon' => [
                        'id_pabellon' => $pabellon->id_pabellon,
                        'nombre_pabellon' => $pabellon->nombre_pabellon,
                        'feria' => $pabellon->feria,
                    ],
                    'stands' => $stands,
                ],
                'meta' => [
                    'total' => $stands->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los stands',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear múltiples stands nuevos
     */
    public function store(Request $request, $pabellonId)
    {
        $validator = Validator::make($request->all(), [
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'area_stand' => ['required', 'numeric', 'min:0.01'],
        ], [
            'cantidad.required' => 'La cantidad de stands es obligatoria.',
            'cantidad.min' => 'Debe crear al menos 1 stand.',
            'cantidad.max' => 'No puede crear más de 100 stands a la vez.',
            'area_stand.required' => 'El área del stand es obligatoria.',
            'area_stand.min' => 'El área debe ser mayor a 0.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            $feriaId = $pabellon->feria;

            DB::beginTransaction();

            // Paso 1: Contar stands existentes para generar numeración correlativa
            $maxNumeroStand = Stand::where('id_pabellon', $pabellonId)
                ->where('feria', $feriaId)
                ->max('numero_stand') ?? 0;

            $standsCreados = [];
            $cantidad = $request->cantidad;

            // Paso 2: Crear los stands uno por uno
            for ($i = 1; $i <= $cantidad; $i++) {
                $numeroStand = $maxNumeroStand + $i;

                $stand = Stand::create([
                    'id_pabellon' => $pabellonId,
                    'feria' => $feriaId,
                    'numero_stand' => $numeroStand,
                    'area_stand' => $request->area_stand,
                    'precio_stand' => 0,
                    'sup' => 0,
                    'izq' => 0,
                    'coord' => '',
                    'tipo' => 0,
                    'anterior' => 0,
                    'lat' => '',
                    'lon' => '',
                ]);

                $standsCreados[] = $stand;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Se crearon {$cantidad} stand(s) correctamente.",
                'data' => $standsCreados,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron crear los stands',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar un stand específico
     */
    public function update(Request $request, $pabellonId, $id)
    {
        $validator = Validator::make($request->all(), [
            'numero_stand' => ['nullable', 'string'],
            'area_stand' => ['nullable', 'numeric', 'min:0.01'],
            'precio_stand' => ['nullable', 'numeric'],
            'sup' => ['nullable', 'integer'],
            'izq' => ['nullable', 'integer'],
            'coord' => ['nullable', 'string'],
            // tipo se calcula automáticamente, no se acepta del request
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            
            $stand = Stand::where('id_pabellon', $pabellonId)
                ->where('feria', $pabellon->feria)
                ->where('id_stand', $id)
                ->firstOrFail();

            // Preparar datos para actualizar
            $dataToUpdate = $request->only([
                'numero_stand',
                'area_stand',
                'precio_stand',
                'sup',
                'izq',
                'coord',
            ]);

            // CALCULAR TIPO AUTOMÁTICAMENTE basado en la lógica del sistema antiguo
            // ────────────────────────────────────────────────────────────────────
            // Lógica heredada: $ax = explode(",", $request->pintado);
            //                  (count($ax) < 6) ? $tipo = 1 : $tipo = 2;
            //
            // Explicación:
            // - Cada punto tiene 2 coordenadas (x,y)
            // - 1 punto = 2 valores → tipo 1 (Reserva)
            // - 2 puntos = 4 valores → tipo 1 (Reserva)
            // - 3 puntos = 6 valores → tipo 2 (Pintado)
            // - 4+ puntos = 8+ valores → tipo 2 (Pintado)
            //
            // Resultado:
            // - tipo 1 = Reserva (hasta 2 puntos, menos de 6 valores)
            // - tipo 2 = Pintado (3 o más puntos, 6 o más valores)
            // ────────────────────────────────────────────────────────────────────
            
            $pintado = $request->input('coord', '');
            
            // Limpiar string: quitar espacios y comas finales
            $pintado = trim($pintado);
            $pintado = rtrim($pintado, ',');
            
            if (empty($pintado)) {
                // Sin coordenadas → Reserva
                $dataToUpdate['tipo'] = 1;
            } else {
                // Separar por comas y contar
                $coordenadas = explode(',', $pintado);
                $cantidadValores = count($coordenadas);
                
                // Aplicar lógica del sistema antiguo exactamente
                $dataToUpdate['tipo'] = ($cantidadValores < 6) ? 1 : 2;
            }

            $stand->update($dataToUpdate);

            return response()->json([
                'success' => true,
                'message' => 'Stand actualizado correctamente.',
                'data' => $stand->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el stand',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un stand
     */
    public function destroy($pabellonId, $id)
    {
        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            
            $stand = Stand::where('id_pabellon', $pabellonId)
                ->where('feria', $pabellon->feria)
                ->where('id_stand', $id)
                ->firstOrFail();

            $stand->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stand eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el stand',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener límites de credenciales de un pabellón (tipo_area)
     */
    public function obtenerLimitesCredenciales($pabellonId)
    {
        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            $feriaId = $pabellon->feria;

            $limites = DB::table('limite_credenciales')
                ->where('id_feria', $feriaId)
                ->where('tipo_area', $pabellonId)
                ->orderBy('limite_sup', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $limites,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los límites de credenciales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar o actualizar múltiples límites de credenciales
     */
    public function guardarLimitesCredencialesMultiples(Request $request, $pabellonId)
    {
        $validator = Validator::make($request->all(), [
            'limites' => ['required', 'array'],
            'limites.*.limite_sup' => ['nullable', 'numeric'],
            'limites.*.cant_credenciales' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            $feriaId = $pabellon->feria;

            DB::beginTransaction();

            foreach ($request->limites as $limite) {
                // Si tiene id significa que ya existe
                if (isset($limite['id']) && $limite['id']) {
                    DB::table('limite_credenciales')
                        ->where('id', $limite['id'])
                        ->update([
                            'limite_sup' => $limite['limite_sup'] ?? null,
                            'cant_credenciales' => $limite['cant_credenciales'] ?? null,
                            'pot_contratada' => 0,
                            'lim_entradas' => 0,
                        ]);
                } else {
                    // Es un nuevo registro
                    DB::table('limite_credenciales')->insert([
                        'id_feria' => $feriaId,
                        'tipo_area' => $pabellonId,
                        'limite_sup' => $limite['limite_sup'] ?? null,
                        'cant_credenciales' => $limite['cant_credenciales'] ?? null,
                        'pot_contratada' => 0,
                        'lim_entradas' => 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Límites de credenciales guardados correctamente.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los límites de credenciales',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Eliminar un límite de credenciales existente
     */
    public function eliminarLimiteCredencial($pabellonId, $id)
    {
        try {
            // Borrar por ID directamente
            $limite = DB::table('limite_credenciales')->where('id', $id)->first();

            if (!$limite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite no encontrado',
                ], 404);
            }

            DB::table('limite_credenciales')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Límite eliminado correctamente.',
                'deleted_id' => $id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el límite',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un límite de credenciales cuando no se dispone del ID
     * Se identifica por feria + tipo_area + limite_sup + cant_credenciales
     */
    public function eliminarLimiteCredencialPorCampos(Request $request, $pabellonId)
    {
        $validator = Validator::make($request->all(), [
            'limite_sup' => ['required', 'numeric'],
            'cant_credenciales' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos para eliminar el límite',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pabellon = Pabellon::findOrFail($pabellonId);
            $feriaId = $pabellon->feria;

            // Borrar solamente una coincidencia (en caso de duplicados) usando SQL crudo con LIMIT 1
            $deleted = DB::affectingStatement(
                'DELETE FROM limite_credenciales WHERE id_feria = ? AND tipo_area = ? AND limite_sup = ? AND cant_credenciales = ? LIMIT 1',
                [
                    $feriaId,
                    $pabellonId,
                    $request->limite_sup,
                    $request->cant_credenciales,
                ]
            );

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el límite a eliminar.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Límite eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el límite',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
