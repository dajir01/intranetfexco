<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckPermission
{
    /**
     * Verifica permisos granulares del usuario con fallback legado.
     *
     * Uso: ->middleware('permission:productos.ver')
     * También soporta varias opciones separadas por coma (OR).
     */
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpException(401, 'No autenticado');
        }

        if (empty($permissions)) {
            throw new HttpException(403, 'Permiso no configurado');
        }

        foreach ($permissions as $permission) {
            $permission = trim($permission);
            if ($permission !== '' && method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw new HttpException(403, 'No autorizado para esta acción');
    }
}
