<?php
// app/Http/Controllers/Api/TenantController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    // ── Listar (só admin_global) ──────────────────────────────────────────────

    public function index()
    {
        try {
            $tenants = Tenant::withCount('users')->orderBy('nome')->get();
            return response()->json($tenants, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Criar empresa ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        try {
            $request->validate([
                'nome' => 'required|string|max:150',
                'slug' => 'required|string|max:50|unique:tenants,slug|alpha_dash',
                'cnpj' => 'nullable|string|max:18',
            ]);

            $tenant = Tenant::create([
                'nome' => $request->nome,
                'slug' => $request->slug,
                'cnpj' => $request->cnpj,
                'ativo'=> true,
            ]);

            return response()->json($tenant, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Atualizar empresa ─────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            $request->validate([
                'nome'  => 'sometimes|string|max:150',
                'slug'  => ['sometimes', 'string', 'max:50', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
                'cnpj'  => 'nullable|string|max:18',
                'ativo' => 'sometimes|boolean',
            ]);

            $tenant->update($request->only(['nome', 'slug', 'cnpj', 'ativo']));

            return response()->json($tenant, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}