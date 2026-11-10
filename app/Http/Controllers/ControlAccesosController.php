<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlAccesosController extends Controller
{
    public function eventos(): JsonResponse
    {
        $eventos = DB::table('eventos as e')
            ->select(
                'e.id_feria',
                'e.nombre_feria',
                'e.fecha_inicio',
                'e.fecha_fin'
            )
            ->orderByRaw('COALESCE(e.fecha_inicio, e.inicio) DESC')
            ->orderByDesc('e.id_feria')
            ->get();

        return response()->json(['success' => true, 'data' => $eventos]);
    }

    public function operadores(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
        ]);

        $operadores = DB::table('usuarios_control')
            ->whereExists(function ($query) use ($validated): void {
                $query->selectRaw('1')
                    ->from('usuarios_control_asignaciones')
                    ->whereColumn('usuarios_control_asignaciones.id_usuario_control', 'usuarios_control.id_usuario_control')
                    ->where('usuarios_control_asignaciones.id_feria', $validated['id_feria']);
            })
            ->orderBy('nombre')
            ->orderBy('usuario')
            ->get(['id_usuario_control', 'nombre', 'usuario'])
            ->map(fn (object $operador): array => [
                'id_usuario_control' => (int) $operador->id_usuario_control,
                'nombre' => $operador->nombre,
                'usuario' => $operador->usuario,
            ]);

        return response()->json(['success' => true, 'data' => $operadores]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $query = $this->consultaIngresos($filters);

        $porCredencial = (clone $query)
            ->select('tipo_credencial', 'modalidad_credencial')
            ->selectRaw('COUNT(*) as ingresos')
            ->selectRaw('COUNT(DISTINCT id_credencial) as personas_unicas')
            ->groupBy('tipo_credencial', 'modalidad_credencial')
            ->orderBy('tipo_credencial')
            ->orderBy('modalidad_credencial')
            ->get()
            ->map(fn (object $registro): array => [
                'tipo_credencial' => $this->tipoCredencialNombre((int) $registro->tipo_credencial),
                'tipo_credencial_codigo' => (int) $registro->tipo_credencial,
                'modalidad_credencial' => $this->modalidadNombre((int) $registro->modalidad_credencial),
                'modalidad_credencial_codigo' => (int) $registro->modalidad_credencial,
                'ingresos' => (int) $registro->ingresos,
                'personas_unicas' => (int) $registro->personas_unicas,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'ingresos' => (int) (clone $query)->count(),
                'personas_unicas' => (int) (clone $query)->distinct('id_credencial')->count('id_credencial'),
                'por_credencial' => $porCredencial,
            ],
        ]);
    }

    public function registros(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $registros = $this->consultaIngresos($filters)
            ->leftJoin('credenciales as c', 'c.id_credencial', '=', 'credencial_ingresos.id_credencial')
            ->leftJoin('expositores as ex', 'ex.id_exp', '=', 'c.id_expositor')
            ->leftJoin('eventos as f', 'f.id_feria', '=', 'credencial_ingresos.id_feria')
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ex.nombre', 'like', '%'.$search.'%')
                        ->orWhere('credencial_ingresos.id_credencial', 'like', '%'.$search.'%')
                        ->orWhere('f.nombre_feria', 'like', '%'.$search.'%');
                });
            })
            ->select(
                'credencial_ingresos.id',
                'credencial_ingresos.id_credencial',
                'credencial_ingresos.id_feria',
                'credencial_ingresos.fecha_ingreso',
                'credencial_ingresos.tipo_credencial',
                'credencial_ingresos.modalidad_credencial',
                'credencial_ingresos.dispositivo_ref',
                'credencial_ingresos.punto_acceso',
                'credencial_ingresos.id_usuario_control',
                'usuario_control.nombre as nombre_operador',
                'usuario_control.usuario as usuario_operador',
                'ex.nombre as nombre_persona',
                'f.nombre_feria'
            )
            ->leftJoin('usuarios_control as usuario_control', 'usuario_control.id_usuario_control', '=', 'credencial_ingresos.id_usuario_control')
            ->orderByDesc('credencial_ingresos.fecha_ingreso')
            ->orderByDesc('credencial_ingresos.id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $registros->getCollection()->transform(fn (object $registro): array => [
            'id' => (int) $registro->id,
            'id_credencial' => (int) $registro->id_credencial,
            'id_feria' => (int) $registro->id_feria,
            'nombre_feria' => $registro->nombre_feria,
            'nombre_persona' => $registro->nombre_persona,
            'fecha_ingreso' => $registro->fecha_ingreso,
            'tipo_credencial' => $this->tipoCredencialNombre((int) $registro->tipo_credencial),
            'modalidad_credencial' => $this->modalidadNombre((int) $registro->modalidad_credencial),
            'dispositivo_ref' => $registro->dispositivo_ref,
            'punto_acceso' => $registro->punto_acceso,
            'id_usuario_control' => $registro->id_usuario_control,
            'nombre_operador' => $registro->nombre_operador,
            'usuario_operador' => $registro->usuario_operador,
        ]);

        return response()->json(['success' => true, 'data' => $registros]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $registros = $this->consultaIngresos($filters)
            ->select('tipo_credencial', 'modalidad_credencial')
            ->selectRaw('COUNT(*) as ingresos')
            ->selectRaw('COUNT(DISTINCT id_credencial) as personas_unicas')
            ->groupBy('tipo_credencial', 'modalidad_credencial')
            ->orderBy('tipo_credencial')
            ->orderBy('modalidad_credencial')
            ->get();

        return response()->streamDownload(function () use ($registros): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo generar el archivo de exportación.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tipo de credencial', 'Modalidad', 'Ingresos', 'Personas únicas'], ',', '"', '');
            foreach ($registros as $registro) {
                fputcsv($output, [
                    $this->tipoCredencialNombre((int) $registro->tipo_credencial),
                    $this->modalidadNombre((int) $registro->modalidad_credencial),
                    (int) $registro->ingresos,
                    (int) $registro->personas_unicas,
                ], ',', '"', '');
            }
            fclose($output);
        }, 'control_accesos_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'id_feria' => ['nullable', 'integer', 'exists:eventos,id_feria'],
            'id_usuario_control' => ['nullable', 'integer', 'exists:usuarios_control,id_usuario_control'],
            'tipo_credencial' => ['nullable', 'integer', 'in:1,2,3,4,5'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
    }

    private function consultaIngresos(array $filters): Builder
    {
        return DB::table('credencial_ingresos')
            ->when($filters['id_feria'] ?? null, fn (Builder $query, int $idFeria) => $query->where('credencial_ingresos.id_feria', $idFeria))
            ->when($filters['id_usuario_control'] ?? null, fn (Builder $query, int $idUsuarioControl) => $query->where('credencial_ingresos.id_usuario_control', $idUsuarioControl))
            ->when($filters['tipo_credencial'] ?? null, fn (Builder $query, int $tipo) => $query->where('credencial_ingresos.tipo_credencial', $tipo))
            ->when($filters['desde'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('credencial_ingresos.fecha_ingreso', '>=', $fecha))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $fecha) => $query->whereDate('credencial_ingresos.fecha_ingreso', '<=', $fecha));
    }

    private function tipoCredencialNombre(int $tipo): string
    {
        return match ($tipo) {
            1 => 'Expositor',
            2 => 'Prensa',
            3 => 'Oficial',
            4 => 'Servicios',
            5 => 'Negocios',
            default => 'Sin tipo definido',
        };
    }

    private function modalidadNombre(int $modalidad): string
    {
        return $modalidad === 0 ? 'Física' : 'Digital';
    }
}
