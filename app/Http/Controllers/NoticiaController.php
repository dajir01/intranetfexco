<?php

namespace App\Http\Controllers;

use App\Events\NoticiaActualizada;
use App\Models\Noticia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NoticiaController extends Controller
{
    /**
     * Mostrar la SPA de noticias (todas las subrutas)
     */
    public function index()
    {
        return view('application');
    }

    /**
     * Obtener noticias con paginación y búsqueda
     */
    public function data(Request $request)
    {
        try {
            $query = Noticia::select(['id_noticia', 'titulo', 'descripcion', 'fecha', 'imagen']);

            // Búsqueda por título o descripción
            if ($request->filled('search')) {
                $searchTerm = '%'.$request->search.'%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('titulo', 'like', $searchTerm)
                        ->orWhere('descripcion', 'like', $searchTerm);
                });
            }

            // Ordenamiento por fecha descendente (más reciente primero)
            $query->orderBy('fecha', 'desc')
                ->orderBy('id_noticia', 'desc');

            // Obtener todas las noticias (el frontend maneja la paginación)
            $noticias = $query->get();

            return response()->json([
                'success' => true,
                'data' => $noticias,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener noticias: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las noticias',
            ], 500);
        }
    }

    /**
     * Obtener una noticia específica
     */
    public function show($id)
    {
        try {
            $noticia = Noticia::find($id);

            if (! $noticia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $noticia,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener noticia: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la noticia',
            ], 500);
        }
    }

    /**
     * Guardar una nueva noticia
     */
    public function store(Request $request)
    {
        try {
            // Validar datos
            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'required|image|mimes:jpeg,png|max:2048',
            ]);

            // Crear noticia
            $noticia = Noticia::create([
                'titulo' => $validated['titulo'],
                'descripcion' => $validated['descripcion'],
                'fecha' => now(),
                'imagen' => '',
            ]);

            // Procesar imagen
            if ($request->hasFile('foto')) {
                $this->procesarYGuardarImagen($request->file('foto'), $noticia->id_noticia);
                $noticia->refresh();
            }

            // Broadcasting en tiempo real
            NoticiaActualizada::dispatch($noticia->id_noticia, 'create', ['titulo' => $noticia->titulo]);

            return response()->json([
                'success' => true,
                'message' => 'Noticia creada correctamente',
                'data' => $noticia,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear noticia: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la noticia',
            ], 500);
        }
    }

    /**
     * Procesar y guardar imagen con redimensionamiento y compresión
     */
    private function procesarYGuardarImagen($file, $noticiaId)
    {
        try {
            $tmpPath = $file->getRealPath();

            // Obtener información de la imagen
            $imageInfo = getimagesize($tmpPath);

            if ($imageInfo === false) {
                throw new \Exception('No se pudo leer la imagen');
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mime = $imageInfo['mime'];

            // Cargar la imagen en GD según el tipo
            switch ($mime) {
                case 'image/jpeg':
                    $src = imagecreatefromjpeg($tmpPath);
                    break;
                case 'image/png':
                    $src = imagecreatefrompng($tmpPath);
                    break;
                default:
                    throw new \Exception('Formato de imagen no soportado. Solo JPG o PNG.');
            }

            if ($src === false) {
                throw new \Exception('Error al cargar la imagen');
            }

            // Dimensiones deseadas
            $newWidth = 960;
            $newHeight = 600;

            // Crear lienzo nuevo con las dimensiones finales
            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Redimensionar (se ajusta a 960x600)
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Generar nombre del archivo
            $filename = $noticiaId.'_'.time().'.jpg';
            $destinationPath = public_path('img/noticias');

            // Crear directorio si no existe
            if (! is_dir($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Eliminar imagen antigua si existe
            $noticia = Noticia::find($noticiaId);
            if ($noticia && $noticia->imagen) {
                $this->eliminarImagenAntigua($noticia->imagen);
            }

            $fullPath = $destinationPath.DIRECTORY_SEPARATOR.$filename;

            // Comprimir iterativamente
            $quality = 85;

            do {
                ob_start();
                imagejpeg($dst, null, $quality);
                $imageData = ob_get_contents();
                ob_end_clean();

                $size = strlen($imageData);
                $quality -= 5;
            } while ($size > 2 * 1024 * 1024 && $quality > 10); // hasta 2MB

            // Guardar imagen en disco
            file_put_contents($fullPath, $imageData);

            // Liberar memoria
            imagedestroy($src);
            imagedestroy($dst);

            // Guardar una ruta local para evitar depender del dominio configurado en APP_URL.
            $imageUrl = '/img/noticias/'.$filename;

            // Actualizar noticia con la URL de la imagen
            Noticia::where('id_noticia', $noticiaId)->update([
                'imagen' => $imageUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al procesar imagen: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar imagen antigua del servidor
     */
    private function eliminarImagenAntigua($imagenUrl)
    {
        try {
            // Extraer el nombre del archivo de la URL
            $filename = basename(parse_url($imagenUrl, PHP_URL_PATH));
            $filePath = public_path('img/noticias/'.$filename);

            // Eliminar archivo si existe
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo eliminar imagen antigua: '.$e->getMessage());
        }
    }

    /**
     * Actualizar una noticia
     */
    public function update(Request $request, $id)
    {
        try {
            $noticia = Noticia::find($id);

            if (! $noticia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada',
                ], 404);
            }

            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'foto' => 'nullable|image|mimes:jpeg,png|max:2048',
            ]);

            // Actualizar campos de texto
            $noticia->update([
                'titulo' => $validated['titulo'],
                'descripcion' => $validated['descripcion'],
            ]);

            // Procesar imagen si existe
            if ($request->hasFile('foto')) {
                $this->procesarYGuardarImagen($request->file('foto'), $noticia->id_noticia);
                $noticia->refresh();
            }

            // Broadcasting en tiempo real
            NoticiaActualizada::dispatch($noticia->id_noticia, 'update', ['titulo' => $noticia->titulo]);

            return response()->json([
                'success' => true,
                'message' => 'Noticia actualizada correctamente',
                'data' => $noticia,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar noticia: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la noticia',
            ], 500);
        }
    }

    /**
     * Eliminar una noticia
     */
    public function destroy($id)
    {
        try {
            $noticia = Noticia::find($id);

            if (! $noticia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada',
                ], 404);
            }

            // Eliminar imagen del servidor si existe
            if ($noticia->imagen) {
                $this->eliminarImagenAntigua($noticia->imagen);
            }

            $noticia->delete();

            // Broadcasting en tiempo real
            NoticiaActualizada::dispatch($id, 'delete', ['titulo' => $noticia->titulo]);

            return response()->json([
                'success' => true,
                'message' => 'Noticia eliminada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar noticia: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la noticia',
            ], 500);
        }
    }
}
