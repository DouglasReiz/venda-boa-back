<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        // Admin global passa por tudo
        if ($user->isAdminGlobal()) {
            return $next($request);
        }

        // Usuário sem tenant cadastrado não pode operar
        if (!$user->tenant_id) {
            return response()->json(['error' => 'Usuário sem empresa vinculada.'], 403);
        }

        // Tenant inativo
        if (!$user->tenant?->ativo) {
            return response()->json(['error' => 'Empresa inativa.'], 403);
        }

        return $next($request);
    }
}
