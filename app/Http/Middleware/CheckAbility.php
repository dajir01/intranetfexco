<?php

namespace App\Http\Middleware;

use App\Support\AreaPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckAbility
{
    /**
     * Verifica que el usuario autenticado tenga la habilidad requerida.
     */
    public function handle(Request $request, Closure $next, string $ability)
    {
        $user = $request->user();

        if (! AreaPermissions::allows($user, $ability)) {
            throw new HttpException(403, 'No autorizado para esta acción');
        }

        return $next($request);
    }
}
