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
        $normalized = mb_strtolower($area ?? '');
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        return strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    /**
     * Determina si el usuario puede ejecutar la habilidad indicada.
     */
    public static function allows(?Usuario $user, string $ability): bool
    {
        return $user ? $user->hasPermission($ability) : false;
    }

    /**
     * El sistema anterior por roles/áreas fue retirado.
     */
    public static function userRole(?Usuario $user): ?string
    {
        return null;
    }

    /**
     * Devuelve un mapa [ability => bool] con los permisos efectivos del usuario.
     */
    public static function effectiveAbilityMap(?Usuario $user): array
    {
        $abilities = config('permissions.abilities', []);
        $result = [];

        foreach (array_keys($abilities) as $ability) {
            if (! $user) {
                $result[$ability] = false;
                continue;
            }

            $result[$ability] = method_exists($user, 'hasPermission')
                ? (bool) $user->hasPermission((string) $ability)
                : self::allows($user, (string) $ability);
        }

        return $result;
    }

    /**
     * Determina si el usuario tiene perfil administrativo para aprobaciones.
     */
    public static function isAdministrativeArea(?Usuario $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $user->isActive()) {
            return false;
        }

        return $user->hasPermission('pagos.approve') || $user->hasPermission('pagos.aprobar');
    }
}
