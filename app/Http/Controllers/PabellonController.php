<?php

namespace App\Http\Controllers;

use App\Models\Pabellon;
use App\Models\Feria;
use App\Models\Stand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PabellonController extends Controller
{
    /**
     * Obtener todos los pabellones de una feria específica
     */
    public function index($feriaId)
    {
        try {
            $feria = Feria::findOrFail($feriaId);
            
            $pabellones = Pabellon::where('feria', $feriaId)
                ->orderBy('nombre_pabellon', 'asc')
                ->get();

            // Agregar cantidad de stands a cada pabellón
            $pabellonesConStands = $pabellones->map(function ($pabellon) use ($feriaId) {
                $cantidadStands = Stand::where('id_pabellon', $pabellon->id_pabellon)
                    ->where('feria', $feriaId)
                    ->count();
                
                return [
                    'id_pabellon' => $pabellon->id_pabellon,
                    'nombre_pabellon' => $pabellon->nombre_pabellon,
                    'feria' => $pabellon->feria,
                    'cantidad_stands' => $cantidadStands,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'feria' => [
                        'id_feria' => $feria->id_feria,
                        'nombre_feria' => $feria->nombre_feria,
                    ],
                    'pabellones' => $pabellonesConStands,
                ],
                'meta' => [
                    'total' => $pabellones->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pabellones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo pabellón
     */
    public function store(Request $request, $feriaId)
    {
        $validator = Validator::make($request->all(), [
            'nombre_pabellon' => ['required', 'string', 'max:255'],
            'mapa' => ['nullable', 'image', 'max:5120'], // máx 5MB
        ], [
            'nombre_pabellon.required' => 'El nombre del pabellón es obligatorio.',
            'nombre_pabellon.max' => 'El nombre no puede exceder 255 caracteres.',
            'mapa.image' => 'El archivo debe ser una imagen válida.',
            'mapa.max' => 'La imagen no puede exceder 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Verificar que la feria existe
            $feria = Feria::findOrFail($feriaId);

            // Paso 1: Crear el registro del pabellón primero para obtener el ID
            $pabellon = Pabellon::create([
                'nombre_pabellon' => $request->nombre_pabellon,
                'feria' => $feriaId,
            ]);

            // Paso 2: Guardar la imagen con el formato del sistema antiguo
            if ($request->hasFile('mapa')) {
                $this->saveMapaImage($request->file('mapa'), $feriaId, $pabellon->id_pabellon);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pabellón creado correctamente.',
                'data' => $pabellon->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el pabellón',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener un pabellón específico
     */
    public function show($feriaId, $id)
    {
        try {
            $pabellon = Pabellon::where('feria', $feriaId)
                ->where('id_pabellon', $id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $pabellon,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pabellón no encontrado',
            ], 404);
        }
    }

    /**
     * Actualizar un pabellón (nombre y/o mapa)
     */
    public function update(Request $request, $feriaId, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_pabellon' => ['required', 'string', 'max:255'],
            'mapa' => ['nullable', 'image', 'max:5120'],
        ], [
            'nombre_pabellon.required' => 'El nombre del pabellón es obligatorio.',
            'nombre_pabellon.max' => 'El nombre no puede exceder 255 caracteres.',
            'mapa.image' => 'El archivo debe ser una imagen válida.',
            'mapa.max' => 'La imagen no puede exceder 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pabellon = Pabellon::where('feria', $feriaId)
                ->where('id_pabellon', $id)
                ->firstOrFail();

            // Actualizar nombre
            $pabellon->update([
                'nombre_pabellon' => $request->nombre_pabellon,
            ]);

            // Actualizar mapa si se envió nueva imagen
            if ($request->hasFile('mapa')) {
                $this->saveMapaImage($request->file('mapa'), $feriaId, $id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pabellón actualizado correctamente.',
                'data' => $pabellon->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el pabellón',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un pabellón
     */
    public function destroy($feriaId, $id)
    {
        try {
            $pabellon = Pabellon::where('feria', $feriaId)
                ->where('id_pabellon', $id)
                ->firstOrFail();

            // Eliminar imagen del sistema antiguo si existe
            $imagePath = public_path("img/pabellones/{$feriaId}_{$id}.png");
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $pabellon->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pabellón eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo eliminar el pabellón',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar imagen del mapa siguiendo la lógica del sistema antiguo
     * Formato: {id_feria}_{id_pabellon}.png
     * Ubicación: /public/img/pabellones/
     */
    private function saveMapaImage($file, $feriaId, $pabellonId)
    {
        // Ruta del directorio donde se guardan los mapas (sistema antiguo)
        $directory = public_path('img/pabellones');

        // Crear directorio si no existe
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Nombre del archivo siguiendo la convención del sistema antiguo
        $filename = "{$feriaId}_{$pabellonId}.png";
        $fullPath = $directory . '/' . $filename;

        // Eliminar archivo existente si ya existe
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Guardar la nueva imagen
        // Convertir a PNG si es necesario
        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        imagepng($image, $fullPath);
        imagedestroy($image);

        return $filename;
    }
}
