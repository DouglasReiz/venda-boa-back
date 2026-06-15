<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $permitido = match ($role) {
            'admin_global' => $user->isAdminGlobal(),
            'admin'        => $user->isAdmin(),
            'operador'     => true, // qualquer role autenticada pode operar
            default        => false,
        };

        if (!$permitido) {
            return response()->json(['error' => 'Acesso não autorizado para seu perfil.'], 403);
        }

        return $next($request);
    }
}
