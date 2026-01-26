<?php

namespace App\Http\Controllers;

use App\Models\Feria;
use App\Models\Pabellon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeriaController extends Controller
{
    /**
     * Obtener listado de ferias con sus pabellones asociados
     */
    public function index(Request $request)
    {
        try {
            // Obtener todas las ferias con sus pabellones, ordenadas por fecha de inicio descendente
            $ferias = Feria::with('pabellones')
                ->orderBy('fecha_inicio', 'desc')
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
        ]);

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

            // Parsear tipos de credenciales a arreglo para facilitar el front
            $tiposArray = array_filter(explode(';', $feria->tipo_credenciales ?? ''), 'strlen');

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => $feria,
                    'tipo_cred' => $tiposArray,
                    'pabellones' => $feria->pabellones,
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

        $feria->update([
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
        ]);

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
