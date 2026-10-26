<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /** Uso: ->middleware('role:admin,empresa') */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->activo || ! in_array($user->role, $roles, true)) {
            abort(403, 'No tienes permiso para acceder aquí.');
        }

        return $next($request);
    }
}
