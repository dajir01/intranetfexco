<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PermissionController extends Controller
{
    /**
     * Devuelve catálogo completo de permisos (lista + agrupación por módulo).
     */
    public function index(): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
        try {
            $permissions = Permission::query()
                ->whereIn('name', $configuredNames)
                ->orderBy('module')
                ->orderBy('action')
                ->get(['id', 'name', 'module', 'action']);

            $grouped = $permissions
                ->groupBy('module')
                ->map(fn ($items) => $items->values())
                ->toArray();

            $missingNames = array_values(array_diff($configuredNames, $permissions->pluck('name')->all()));
            if ($missingNames !== []) {
                Log::warning('El catálogo de permisos configurados no está completamente sincronizado con la base de datos.', [
                    'permisos_faltantes' => $missingNames,
                    'cantidad_configurada' => count($configuredNames),
                    'cantidad_encontrada' => $permissions->count(),
                ]);
            }

            return response()->json([
                'data' => [
                    'permissions' => $permissions,
                    'grouped' => $grouped,
                    'missing_permissions' => $missingNames,
                    'catalog_synced' => $missingNames === [],
                ],
            ]);
        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('No se pudo cargar el catálogo de permisos.', [
                'error_id' => $errorId,
                'actor_id' => request()->user()?->id_usuario,
                'permisos_configurados' => count($configuredNames),
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => "No se pudo cargar el catálogo de permisos. Código de seguimiento: {$errorId}.",
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Devuelve permisos asignados a un usuario.
     */
    public function userPermissions(int $id): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
        try {
            $user = Usuario::query()
                ->with(['permissions:id,name,module,action'])
                ->findOrFail($id);

            return response()->json([
                'data' => [
                    'user' => [
                        'id_usuario' => (int) $user->id_usuario,
                        'nombre_usuario' => $user->nombre_usuario,
                        'nick_usuario' => $user->nick_usuario,
                        'email' => $user->email,
                        'area' => $user->area,
                    ],
                    'permissions' => $user->permissions
                        ->whereIn('name', $configuredNames)
                        ->pluck('name')
                        ->values(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('No se pudieron consultar los permisos asignados a un usuario.', [
                'error_id' => $errorId,
                'usuario_destino_id' => $id,
                'actor_id' => request()->user()?->id_usuario,
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => "No se pudieron consultar los permisos. Código de seguimiento: {$errorId}.",
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Sincroniza permisos de un usuario.
     */
    public function syncUserPermissions(Request $request, int $id): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
        try {
            $validated = $request->validate([
                'permissions' => ['required', 'array'],
                'permissions.*' => ['string', 'distinct', Rule::in($configuredNames)],
            ], [
                'permissions.required' => 'Debe enviar la lista de permisos del usuario.',
                'permissions.array' => 'La lista de permisos debe tener un formato válido.',
                'permissions.*.string' => 'Cada permiso seleccionado debe ser texto.',
                'permissions.*.distinct' => 'La lista contiene permisos duplicados.',
                'permissions.*.in' => 'Uno o más permisos no pertenecen al catálogo autorizado.',
            ]);
        } catch (ValidationException $e) {
            Log::warning('Datos inválidos al asignar permisos a un usuario.', [
                'usuario_destino_id' => $id,
                'actor_id' => $request->user()?->id_usuario,
                'permisos_solicitados' => $request->input('permissions'),
                'errores_validacion' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            throw $e;
        }

        try {
            $user = Usuario::query()->findOrFail($id);
            $permissionNames = array_values($validated['permissions']);
            $actor = $request->user();
            $missingNames = [];

            abort_unless($actor instanceof Usuario, 401);

            $actor->loadMissing('permissions:id,name');
            abort_unless(
                $actor->hasPermission('usuarios.gestionar_permisos'),
                403,
                'No autorizado para gestionar permisos.'
            );

            DB::transaction(function () use ($user, $permissionNames, &$missingNames): void {
                $permissions = Permission::query()
                    ->whereIn('name', $permissionNames)
                    ->get(['id', 'name']);
                $missingNames = array_values(array_diff($permissionNames, $permissions->pluck('name')->all()));
                if ($missingNames !== []) {
                    throw new \DomainException(
                        'No existen en la base de datos estos permisos configurados: '.implode(', ', $missingNames)
                    );
                }

                $user->permissions()->sync($permissions->pluck('id')->all());
            });

            $user->load(['permissions:id,name,module,action']);

            return response()->json([
                'message' => 'Permisos actualizados correctamente',
                'data' => [
                    'id_usuario' => (int) $user->id_usuario,
                    'permissions' => $user->permissions
                        ->whereIn('name', $configuredNames)
                        ->pluck('name')
                        ->values(),
                ],
            ]);
        } catch (\DomainException $e) {
            Log::error('No se pudieron asignar permisos porque el catálogo de base de datos está incompleto.', [
                'usuario_destino_id' => $id,
                'actor_id' => $request->user()?->id_usuario,
                'mensaje' => $e->getMessage(),
                'permisos_solicitados' => $request->input('permissions'),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'missing_permissions' => array_values(array_filter(
                    $missingNames,
                    fn (string $name): bool => in_array($name, $permissionNames, true),
                )),
            ], 422);
        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('Error inesperado al guardar permisos de usuario.', [
                'error_id' => $errorId,
                'usuario_destino_id' => $id,
                'actor_id' => $request->user()?->id_usuario,
                'permisos_solicitados' => $request->input('permissions'),
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => "No se pudieron guardar los permisos. Código de seguimiento: {$errorId}.",
                'error_id' => $errorId,
            ], 500);
        }
    }

    private function configuredPermissionNames(): array
    {
        return collect(config('acl.permissions', []))
            ->map(fn (array $permission): string => trim(($permission['module'] ?? '').'.'.($permission['action'] ?? ''), '.'))
            ->filter()
            ->values()
            ->all();
    }
}
