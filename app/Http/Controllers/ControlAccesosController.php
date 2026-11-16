<?php

namespace App\Http\Controllers;

use App\Exports\ControlAccesosExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

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
        $totales = (clone $query)
            ->selectRaw('COUNT(*) as ingresos')
            ->selectRaw('COUNT(DISTINCT id_credencial) as credenciales_unicas')
            ->first();

        $porCredencial = (clone $query)
            ->select('tipo_credencial', 'modalidad_credencial')
            ->selectRaw('COUNT(*) as ingresos')
            ->selectRaw('COUNT(DISTINCT id_credencial) as credenciales_unicas')
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
                'credenciales_unicas' => (int) $registro->credenciales_unicas,
            ])
            ->values();

        $ingresosPorDia = $this->consultaIngresosPorDia($query)
            ->get()
            ->map(fn (object $registro): array => [
                'fecha' => $registro->fecha,
                'tipo_credencial' => $this->tipoCredencialNombre((int) $registro->tipo_credencial),
                'tipo_credencial_codigo' => (int) $registro->tipo_credencial,
                'ingresos' => (int) $registro->ingresos,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'ingresos' => (int) $totales->ingresos,
                'credenciales_unicas' => (int) $totales->credenciales_unicas,
                'por_credencial' => $porCredencial,
                'ingresos_por_dia' => $ingresosPorDia,
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
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ex.nombre', 'like', '%'.$search.'%')
                        ->orWhere('ex.ci', 'like', '%'.$search.'%');
                });
            })
            ->select(
                'credencial_ingresos.id',
                'credencial_ingresos.fecha_ingreso',
                'credencial_ingresos.tipo_credencial',
                'credencial_ingresos.modalidad_credencial',
                'credencial_ingresos.dispositivo_ref',
                'credencial_ingresos.punto_acceso',
                'credencial_ingresos.id_usuario_control',
                'usuario_control.nombre as nombre_operador',
                'usuario_control.usuario as usuario_operador',
                'ex.nombre as nombre_persona',
                'ex.ci as ci_persona'
            )
            ->leftJoin('usuarios_control as usuario_control', 'usuario_control.id_usuario_control', '=', 'credencial_ingresos.id_usuario_control')
            ->orderByDesc('credencial_ingresos.fecha_ingreso')
            ->orderByDesc('credencial_ingresos.id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $registros->getCollection()->transform(fn (object $registro): array => [
            'id' => (int) $registro->id,
            'nombre_persona' => $registro->nombre_persona,
            'ci_persona' => $registro->ci_persona,
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

    public function exportar(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($validated['search'] ?? '');

        $query = $this->consultaIngresos($filters)
            ->leftJoin('credenciales as c', 'c.id_credencial', '=', 'credencial_ingresos.id_credencial')
            ->leftJoin('expositores as ex', 'ex.id_exp', '=', 'c.id_expositor')
            ->leftJoin('empresas as emp', 'emp.id_empresa', '=', 'c.id_empresa')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ex.nombre', 'like', '%'.$search.'%')
                        ->orWhere('ex.ci', 'like', '%'.$search.'%');
                });
            });

        $resumen = $this->consultaIngresosPorDia($query)
            ->get()
            ->map(fn (object $registro): array => [
                $this->tipoCredencialNombre((int) $registro->tipo_credencial),
                (string) $registro->fecha,
                (int) $registro->ingresos,
            ])
            ->values();

        $detalle = (clone $query)
            ->select(
                'credencial_ingresos.id',
                'credencial_ingresos.fecha_ingreso',
                'credencial_ingresos.numero_puerta',
                'credencial_ingresos.punto_acceso',
                'ex.nombre as nombre_completo',
                'ex.ci',
                'emp.nombre_empresa'
            )
            ->orderBy('credencial_ingresos.fecha_ingreso')
            ->orderBy('credencial_ingresos.id');

        $nombreEvento = (string) DB::table('eventos')
            ->where('id_feria', $filters['id_feria'])
            ->value('nombre_feria');
        $nombreArchivo = 'ControlAccesos_'.(Str::slug($nombreEvento, '_') ?: 'evento_'.$filters['id_feria'])
            .'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ControlAccesosExport($resumen, $detalle), $nombreArchivo);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'id_usuario_control' => ['nullable', 'integer', 'exists:usuarios_control,id_usuario_control'],
            'tipo_credencial' => ['nullable', 'integer', 'in:1,2,3,4,5'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
    }

    private function consultaIngresos(array $filters): Builder
    {
        return DB::table('credencial_ingresos')
            ->when($filters['id_feria'] ?? null, fn (Builder $query, int $idFeria) => $query->where('credencial_ingresos.id_feria', $idFeria))
            ->when($filters['id_usuario_control'] ?? null, fn (Builder $query, int $idUsuarioControl) => $query->where('credencial_ingresos.id_usuario_control', $idUsuarioControl))
            ->when($filters['tipo_credencial'] ?? null, fn (Builder $query, int $tipo) => $query->where('credencial_ingresos.tipo_credencial', $tipo))
            ->when($filters['desde'] ?? null, fn (Builder $query, string $fecha) => $query->where(
                'credencial_ingresos.fecha_ingreso',
                '>=',
                CarbonImmutable::createFromFormat('!Y-m-d', $fecha)->toDateTimeString()
            ))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $fecha) => $query->where(
                'credencial_ingresos.fecha_ingreso',
                '<',
                CarbonImmutable::createFromFormat('!Y-m-d', $fecha)->addDay()->toDateTimeString()
            ));
    }

    private function consultaIngresosPorDia(Builder $query): Builder
    {
        return (clone $query)
            ->selectRaw('DATE(credencial_ingresos.fecha_ingreso) as fecha')
            ->addSelect('credencial_ingresos.tipo_credencial')
            ->selectRaw('COUNT(*) as ingresos')
            ->groupByRaw('DATE(credencial_ingresos.fecha_ingreso), credencial_ingresos.tipo_credencial')
            ->orderBy('fecha')
            ->orderBy('credencial_ingresos.tipo_credencial');
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
