<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission($permission)) {
            return response()->json([
                'codigo' => 403,
                'mensaje' => 'No tienes permiso para realizar esta operación.',
                'datos' => null,
            ], 403);
        }

        return $next($request);
    }
}
