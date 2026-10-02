<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Devuelve catálogo completo de permisos (lista + agrupación por módulo).
     */
    public function index(): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
        $permissions = Permission::query()
            ->whereIn('name', $configuredNames)
            ->orderBy('module')
            ->orderBy('action')
            ->get(['id', 'name', 'module', 'action']);

        $grouped = $permissions
            ->groupBy('module')
            ->map(fn ($items) => $items->values())
            ->toArray();

        return response()->json([
            'data' => [
                'permissions' => $permissions,
                'grouped' => $grouped,
            ],
        ]);
    }

    /**
     * Devuelve permisos asignados a un usuario.
     */
    public function userPermissions(int $id): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
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
    }

    /**
     * Sincroniza permisos de un usuario.
     */
    public function syncUserPermissions(Request $request, int $id): JsonResponse
    {
        $configuredNames = $this->configuredPermissionNames();
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($configuredNames)],
        ]);

        $user = Usuario::query()->findOrFail($id);
        $permissionNames = array_values($validated['permissions']);
        $actor = $request->user();

        abort_unless($actor instanceof Usuario, 401);

        $actor->loadMissing('permissions:id,name');
        $user->loadMissing('permissions:id,name');
        $currentPermissionNames = $user->permissions->pluck('name')->all();
        $notDelegableNew = array_values(array_filter(
            $permissionNames,
            fn (string $permission): bool => ! $actor->hasPermission($permission)
                && ! in_array($permission, $currentPermissionNames, true)
        ));

        abort_if($notDelegableNew !== [], 403, 'No puede asignar permisos que no tiene asignados.');

        $protectedPermissionNames = array_values(array_filter(
            $currentPermissionNames,
            fn (string $permission): bool => ! $actor->hasPermission($permission)
        ));
        $permissionNames = array_values(array_unique(array_merge(
            array_filter($permissionNames, fn (string $permission): bool => $actor->hasPermission($permission)),
            $protectedPermissionNames
        )));

        DB::transaction(function () use ($user, $permissionNames): void {
            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id')
                ->all();

            $user->permissions()->sync($permissionIds);
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
