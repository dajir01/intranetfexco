<?php

namespace App\Http\Controllers;

use App\Models\UsuarioControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ControlAccesosOperadoresController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['nombre', 'usuario', 'activo'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $operators = UsuarioControl::query()
            ->whereExists(function ($query) use ($validated): void {
                $query->selectRaw('1')
                    ->from('usuarios_control_asignaciones')
                    ->whereColumn('usuarios_control_asignaciones.id_usuario_control', 'usuarios_control.id_usuario_control')
                    ->where('usuarios_control_asignaciones.id_feria', $validated['id_feria']);
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nombre', 'like', '%'.$search.'%')
                        ->orWhere('usuario', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($validated['sort_by'] ?? 'nombre', $validated['sort_order'] ?? 'asc')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $ids = $operators->getCollection()->pluck('id_usuario_control');
        $permissions = DB::table('usuarios_control_permisos')
            ->whereIn('id_usuario_control', $ids)
            ->get(['id_usuario_control', 'permiso'])
            ->groupBy('id_usuario_control');
        $assignments = DB::table('usuarios_control_asignaciones as asignacion')
            ->whereIn('asignacion.id_usuario_control', $ids)
            ->where('asignacion.id_feria', $validated['id_feria'])
            ->orderBy('asignacion.numero_puerta')
            ->get([
                'asignacion.id_usuario_control',
                'asignacion.numero_puerta',
            ])
            ->groupBy('id_usuario_control');

        $data = $operators->getCollection()->map(fn (UsuarioControl $operator): array => [
            'id_usuario_control' => (int) $operator->id_usuario_control,
            'nombre' => $operator->nombre,
            'usuario' => $operator->usuario,
            'activo' => (bool) $operator->activo,
            'permissions' => ($permissions->get($operator->id_usuario_control) ?? collect())
                ->pluck('permiso')
                ->values(),
            'puertas' => ($assignments->get($operator->id_usuario_control) ?? collect())
                ->pluck('numero_puerta')
                ->map(fn (int $door): int => (int) $door)
                ->values(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $operators->total(),
                'per_page' => $operators->perPage(),
                'current_page' => $operators->currentPage(),
                'last_page' => $operators->lastPage(),
            ],
            'permissions_catalog' => $this->permissionsCatalog(),
        ]);
    }

    public function eventos(): JsonResponse
    {
        $events = DB::table('eventos')
            ->orderByRaw('COALESCE(fecha_inicio, inicio) DESC')
            ->orderByDesc('id_feria')
            ->get(['id_feria', 'nombre_feria', 'puertas_acceso'])
            ->map(fn (object $event): array => [
                'id_feria' => (int) $event->id_feria,
                'nombre_feria' => $event->nombre_feria,
                'puertas' => $this->availableDoors($event->puertas_acceso),
            ])
            ->values();

        return response()->json(['data' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $operator = UsuarioControl::query()->findOrFail($id);

        return $this->save($request, $operator);
    }

    private function save(Request $request, ?UsuarioControl $operator): JsonResponse
    {
        $isCreating = $operator === null;
        $operation = $isCreating ? 'crear' : 'actualizar';
        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'usuario' => mb_strtolower(trim((string) $request->input('usuario'))),
        ]);

        try {
            $permissionNames = array_keys(config('controlaccesos.operador_permissions', []));
            $usernameRule = Rule::unique('usuarios_control', 'usuario');
            if ($operator) {
                $usernameRule->ignore($operator->id_usuario_control, 'id_usuario_control');
            }

            $validated = $request->validate([
                'nombre' => ['required', 'string', 'max:150'],
                'usuario' => [
                    'required',
                    'string',
                    'alpha_dash:ascii',
                    'max:100',
                    $usernameRule,
                ],
                'id_feria' => ['required', 'integer', 'exists:eventos,id_feria'],
                'password' => $operator
                    ? ['sometimes', 'nullable', 'string', 'min:4', 'max:8']
                    : ['required', 'string', 'min:4', 'max:8'],
                'activo' => ['sometimes', 'boolean'],
                'permissions' => ['required', 'array'],
                'permissions.*' => ['required', 'string', 'distinct', Rule::in($permissionNames)],
                'puertas' => ['required', 'array', 'min:1', 'max:128'],
                'puertas.*' => ['required', 'integer', 'distinct', 'min:1', 'max:65535'],
            ]);

            $eventId = (int) $validated['id_feria'];
            $doors = $validated['puertas'];
            $this->validateDoors($doors);

            $savedOperator = DB::transaction(function () use ($operator, $validated, $eventId, $doors, $isCreating): UsuarioControl {
                $this->validateAssignmentDoors($eventId, $doors);
                $operator ??= new UsuarioControl();
                $operator->nombre = $validated['nombre'];
                $operator->usuario = $validated['usuario'];
                if (isset($validated['password']) && $validated['password'] !== '') {
                    $operator->password = $validated['password'];
                }
                if ($isCreating || array_key_exists('activo', $validated)) {
                    $operator->activo = (bool) ($validated['activo'] ?? true);
                }
                $operator->save();

                DB::table('usuarios_control_permisos')
                    ->where('id_usuario_control', $operator->id_usuario_control)
                    ->delete();
                DB::table('usuarios_control_asignaciones')
                    ->where('id_usuario_control', $operator->id_usuario_control)
                    ->where('id_feria', $eventId)
                    ->delete();

                $now = now();
                $permissionRows = array_map(fn (string $permission): array => [
                    'id_usuario_control' => $operator->id_usuario_control,
                    'permiso' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $validated['permissions']);
                if ($permissionRows !== []) {
                    DB::table('usuarios_control_permisos')->insert($permissionRows);
                }

                $assignmentRows = array_map(fn (int $door): array => [
                    'id_usuario_control' => $operator->id_usuario_control,
                    'id_feria' => $eventId,
                    'numero_puerta' => $door,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $doors);
                if ($assignmentRows !== []) {
                    DB::table('usuarios_control_asignaciones')->insert($assignmentRows);
                }

                return $operator;
            });

            return response()->json([
                'message' => $isCreating
                    ? 'Operador creado correctamente.'
                    : 'Operador actualizado correctamente.',
                'data' => [
                    'id_usuario_control' => (int) $savedOperator->id_usuario_control,
                    'nombre' => $savedOperator->nombre,
                    'usuario' => $savedOperator->usuario,
                    'activo' => (bool) $savedOperator->activo,
                ],
            ], $isCreating ? 201 : 200);
        } catch (ValidationException $exception) {
            Log::warning('Validación rechazada al guardar operador de acceso.', [
                'operacion' => $operation,
                'usuario_id' => data_get($request->user(), 'id_usuario'),
                'id_usuario_control' => $operator?->id_usuario_control,
                'id_feria' => $request->input('id_feria'),
                'ruta' => $request->path(),
                'metodo' => $request->method(),
                'ip' => $request->ip(),
                'errores_validacion' => $exception->errors(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $errorId = (string) Str::uuid();
            Log::error('Error inesperado al guardar operador de acceso.', [
                'error_id' => $errorId,
                'operacion' => $operation,
                'usuario_id' => data_get($request->user(), 'id_usuario'),
                'id_usuario_control' => $operator?->id_usuario_control,
                'id_feria' => $request->input('id_feria'),
                'ruta' => $request->path(),
                'metodo' => $request->method(),
                'ip' => $request->ip(),
                'excepcion' => $exception::class,
                'mensaje' => $exception->getMessage(),
                'archivo' => $exception->getFile(),
                'linea' => $exception->getLine(),
                'stack_trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => "No se pudo {$operation} el operador. Consulta el registro con la referencia {$errorId}.",
                'error_id' => $errorId,
            ], 500);
        }
    }

    private function permissionsCatalog(): array
    {
        return collect(config('controlaccesos.operador_permissions', []))
            ->map(fn (string $label, string $permission): array => [
                'permission' => $permission,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function availableDoors(?string $doorList): array
    {
        if ($doorList === null || trim($doorList) === '') {
            return [];
        }

        return collect(explode(';', $doorList))
            ->map(fn (string $door): string => trim($door))
            ->filter(fn (string $door): bool => ctype_digit($door) && (int) $door > 0 && (int) $door <= 65535)
            ->map(fn (string $door): int => (int) $door)
            ->unique()
            ->values()
            ->all();
    }

    private function validateDoors(array $doors): void
    {
        if (count($doors) !== count(array_unique($doors))) {
            throw ValidationException::withMessages(['puertas' => 'No se puede repetir una puerta.']);
        }
    }

    private function validateAssignmentDoors(int $eventId, array $doors): void
    {
        $event = DB::table('eventos')
            ->where('id_feria', $eventId)
            ->lockForUpdate()
            ->first(['puertas_acceso']);
        if (! $event) {
            throw ValidationException::withMessages(['id_feria' => 'El evento seleccionado ya no existe.']);
        }

        $availableDoors = $this->availableDoors($event->puertas_acceso);
        foreach ($doors as $index => $door) {
            if (! in_array((int) $door, $availableDoors, true)) {
                throw ValidationException::withMessages([
                    "puertas.{$index}" => 'La puerta no está habilitada para el evento seleccionado.',
                ]);
            }
        }
    }
}
