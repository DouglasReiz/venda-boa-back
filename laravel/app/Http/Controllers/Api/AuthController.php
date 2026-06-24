<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Impede login de usuários desativados
        if (!$user->ativo) {
            Auth::logout();
            return response()->json(['message' => 'Conta desativada. Entre em contato com o administrador.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token'          => $token,
            'user'           => $user->load('tenant'),
            'primeiro_acesso'=> (bool) $user->primeiro_acesso, // frontend redireciona se true
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado.'], 200);
    }
}