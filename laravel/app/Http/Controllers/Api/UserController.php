<?php
// app/Http/Controllers/Api/UserController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BoasVindasMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ── Listar usuários ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $query = User::with('tenant')->orderBy('name');

            if (!$request->user()->isAdminGlobal()) {
                $query->where('tenant_id', $request->user()->tenant_id);
            }

            return response()->json($query->get(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Criar usuário ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        try {
            $isAdminGlobal = $request->user()->isAdminGlobal();

            $rules = [
                'name'      => 'required|string|max:100',
                'email'     => 'required|email|unique:users,email',
                'password'  => 'required|string|min:6',
                'role'      => $isAdminGlobal
                    ? ['required', Rule::in(['admin_global', 'admin', 'operador'])]
                    : ['required', Rule::in(['admin', 'operador'])],
            ];

            if ($isAdminGlobal) {
                $rules['tenant_id'] = 'nullable|exists:tenants,id';
            }

            $request->validate($rules);

            $tenantId = $isAdminGlobal
                ? $request->tenant_id
                : $request->user()->tenant_id;

            if ($request->role !== 'admin_global' && !$tenantId) {
                return response()->json(['error' => 'Selecione uma empresa para este usuário.'], 422);
            }

            $senhaTemporaria = $request->password;

            $user = User::create([
                'tenant_id'      => $request->role === 'admin_global' ? null : $tenantId,
                'name'           => $request->name,
                'email'          => $request->email,
                'password'       => Hash::make($senhaTemporaria),
                'role'           => $request->role,
                'ativo'          => true,
                'primeiro_acesso' => true, // força troca de senha no primeiro login
            ]);

            // Envia e-mail de boas-vindas com as credenciais
            $nomeEmpresa = $user->tenant?->nome ?? config('app.name', 'VendaBoa');
            Mail::to($user->email)->send(
                new BoasVindasMail($user, $senhaTemporaria, $nomeEmpresa)
            );

            return response()->json($user->load('tenant'), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Atualizar usuário ─────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        try {
            $user          = $this->findUser($request, $id);
            $isAdminGlobal = $request->user()->isAdminGlobal();

            $rules = [
                'name'     => 'sometimes|string|max:100',
                'email'    => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => 'nullable|string|min:6',
                'ativo'    => 'sometimes|boolean',
            ];

            if ($isAdminGlobal) {
                $rules['role']      = ['sometimes', Rule::in(['admin_global', 'admin', 'operador'])];
                $rules['tenant_id'] = 'nullable|exists:tenants,id';
            } else {
                $rules['role'] = ['sometimes', Rule::in(['admin', 'operador'])];
            }

            $request->validate($rules);

            $dados = $request->only(['name', 'email', 'ativo']);

            if ($request->filled('password')) {
                $dados['password']        = Hash::make($request->password);
                $dados['primeiro_acesso'] = false; // admin resetou a senha manualmente
            }

            if ($request->has('role')) {
                $dados['role'] = $request->role;
                if ($request->role === 'admin_global') {
                    $dados['tenant_id'] = null;
                }
            }

            if ($isAdminGlobal && $request->has('tenant_id') && $request->role !== 'admin_global') {
                $dados['tenant_id'] = $request->tenant_id;
            }

            $user->update($dados);

            return response()->json($user->load('tenant'), 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Ativar / desativar ────────────────────────────────────────────────────

    public function toggleAtivo(Request $request, $id)
    {
        try {
            $user = $this->findUser($request, $id);

            if ($user->id === $request->user()->id) {
                return response()->json(['error' => 'Você não pode desativar sua própria conta.'], 422);
            }

            $user->update(['ativo' => !$user->ativo]);

            return response()->json([
                'message' => $user->ativo ? 'Usuário ativado.' : 'Usuário desativado.',
                'ativo'   => $user->ativo,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Trocar senha (primeiro acesso) ────────────────────────────────────────

    public function trocarSenha(Request $request)
    {
        try {
            $request->validate([
                'senha_atual' => 'required|string',
                'nova_senha'  => 'required|string|min:6|confirmed', // nova_senha_confirmation
            ]);

            $user = $request->user();

            if (!Hash::check($request->senha_atual, $user->password)) {
                return response()->json(['error' => 'Senha atual incorreta.'], 422);
            }

            if ($request->senha_atual === $request->nova_senha) {
                return response()->json(['error' => 'A nova senha deve ser diferente da senha atual.'], 422);
            }

            $user->update([
                'password'        => Hash::make($request->nova_senha),
                'primeiro_acesso' => false,
            ]);

            return response()->json(['message' => 'Senha alterada com sucesso!'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function findUser(Request $request, $id): User
    {
        $query = User::where('id', $id);
        if (!$request->user()->isAdminGlobal()) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }
        return $query->firstOrFail();
    }
}
