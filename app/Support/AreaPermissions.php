<?php

namespace App\Support;

use App\Models\Usuario;

class AreaPermissions
{
    /**
     * Normaliza el nombre de área a minúsculas para comparación.
     */
    public static function normalizeArea(?string $area): string
    {
        return trim(mb_strtolower($area ?? ''));
    }

    /**
     * Resuelve el rol interno a partir del nombre de área.
     */
    public static function resolveRole(?string $area): ?string
    {
        $normalized = self::normalizeArea($area);
        foreach (config('permissions.role_groups', []) as $role => $areas) {
            if (in_array($normalized, $areas ?? [], true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Determina si el usuario puede ejecutar la habilidad indicada.
     */
    public static function allows(?Usuario $user, string $ability): bool
    {
        if (! $user) {
            return false;
        }

        $role = self::resolveRole($user->area ?? null);
        if (! $role) {
            return false;
        }

        // Roles de acceso total: todo permitido.
        if ($role === 'full') {
            return true;
        }

        $allowedRoles = config('permissions.abilities')[$ability] ?? [];

        return in_array($role, $allowedRoles, true);
    }

    /**
     * Devuelve el rol interno del usuario.
     */
    public static function userRole(?Usuario $user): ?string
    {
        return $user ? self::resolveRole($user->area ?? null) : null;
    }
}
