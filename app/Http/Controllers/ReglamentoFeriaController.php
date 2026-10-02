<?php

namespace App\Http\Controllers;

use App\Models\Feria;
use App\Models\ReglamentoFeria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReglamentoFeriaController extends Controller
{
    public function ferias()
    {
        $ferias = Feria::query()
            ->select('id_feria', 'nombre_feria', 'estado_feria')
            ->orderByFechaEvento()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ferias,
        ]);
    }

    public function index(int $feriaId)
    {
        Feria::findOrFail($feriaId);

        $reglamentos = ReglamentoFeria::query()
            ->where('id_feria', $feriaId)
            ->orderByDesc('created_at')
            ->get(['id_reglamento', 'nombre_reglamento', 'descripcion', 'nombre_archivo', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $reglamentos,
        ]);
    }

    public function store(Request $request, int $feriaId)
    {
        Feria::findOrFail($feriaId);

        $validated = $request->validate([
            'nombre_reglamento' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'nombre_reglamento.required' => 'El nombre del reglamento es obligatorio.',
            'nombre_reglamento.max' => 'El nombre no puede superar los 255 caracteres.',
            'descripcion.required' => 'La descripción del reglamento es obligatoria.',
            'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
            'archivo.required' => 'Debe seleccionar un archivo PDF.',
            'archivo.mimes' => 'Solo se permiten archivos PDF.',
            'archivo.max' => 'El archivo no puede superar los 20 MB.',
        ]);

        $file = $validated['archivo'];
        $nombreOriginal = Str::limit($file->getClientOriginalName(), 255, '');
        $nombreGuardado = Str::uuid() . '.pdf';
        $ruta = "reglamentos-feria/{$feriaId}/{$nombreGuardado}";
        $directorio = public_path("reglamentos-feria/{$feriaId}");
        File::ensureDirectoryExists($directorio);
        $file->move($directorio, $nombreGuardado);

        try {
            $reglamento = ReglamentoFeria::create([
                'id_feria' => $feriaId,
                'nombre_reglamento' => trim($validated['nombre_reglamento']),
                'descripcion' => trim($validated['descripcion']),
                'nombre_archivo' => $nombreOriginal,
                'ruta_archivo' => $ruta,
            ]);
        } catch (\Throwable $exception) {
            File::delete(public_path($ruta));
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Reglamento guardado correctamente.',
            'data' => $reglamento->only(['id_reglamento', 'id_feria', 'nombre_reglamento', 'descripcion', 'nombre_archivo', 'created_at']),
        ], 201);
    }

    public function download(int $id)
    {
        $reglamento = ReglamentoFeria::findOrFail($id);
        $ruta = str_replace('\\', '/', ltrim($reglamento->ruta_archivo, '/'));
        abort_unless(str_starts_with($ruta, 'reglamentos-feria/') && ! in_array('..', explode('/', $ruta), true), 404);

        $rutaPublica = public_path($ruta);
        if (is_file($rutaPublica)) {
            return response()->download($rutaPublica, $reglamento->nombre_archivo, [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort_unless(Storage::disk('local')->exists($ruta), 404);

        return response()->streamDownload(static function () use ($ruta): void {
            $stream = Storage::disk('local')->readStream($ruta);
            if ($stream === false) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, $reglamento->nombre_archivo, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(int $id)
    {
        $reglamento = ReglamentoFeria::findOrFail($id);
        $rutaArchivo = $reglamento->ruta_archivo;

        DB::transaction(function () use ($reglamento, $rutaArchivo) {
            $reglamento->delete();

            if ($rutaArchivo) {
                $ruta = str_replace('\\', '/', ltrim($rutaArchivo, '/'));
                if (! str_starts_with($ruta, 'reglamentos-feria/') || in_array('..', explode('/', $ruta), true)) {
                    throw new \RuntimeException('La ruta del archivo PDF no es válida.');
                }

                $rutaPublica = public_path($ruta);
                if (is_file($rutaPublica)) {
                    if (! File::delete($rutaPublica)) {
                        throw new \RuntimeException('No se pudo eliminar el archivo PDF del almacenamiento.');
                    }
                } elseif (Storage::disk('local')->exists($ruta) && ! Storage::disk('local')->delete($ruta)) {
                    throw new \RuntimeException('No se pudo eliminar el archivo PDF del almacenamiento.');
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Reglamento y archivo PDF eliminados correctamente.',
        ]);
    }
}
