<?php
// app/Http/Controllers/Api/StockController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    // ── Alertas de estoque baixo ──────────────────────────────────────────────

    /**
     * Retorna variantes com estoque <= estoque_minimo.
     * Usado no dashboard e no PDV para exibir alertas.
     */
    public function alertas(Request $request)
    {
        try {
            $query = ProductVariant::with(['product.category'])
                ->whereHas('product', function ($q) use ($request) {
                    $q->where('ativo', true);
                    if (!$request->user()->isAdminGlobal()) {
                        $q->where('tenant_id', $request->user()->tenant_id);
                    }
                })
                ->where('ativo', true)
                ->whereColumn('estoque', '<=', 'estoque_minimo')
                ->orderBy('estoque');

            return response()->json($query->get()->map(fn($v) => [
                'id'             => $v->id,
                'produto'        => $v->product->nome,
                'variante'       => $v->is_default ? null : $v->nome,
                'categoria'      => $v->product->category->nome ?? '—',
                'estoque'        => $v->estoque,
                'estoque_minimo' => $v->estoque_minimo,
                'zerado'         => $v->estaZerado(),
            ]), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Ajuste manual de estoque ──────────────────────────────────────────────

    /**
     * Repõe ou ajusta o estoque de uma variante.
     * Body: { quantidade: 10, motivo: "Reposição de fornecedor" }
     */
    public function ajustar(Request $request, $variantId)
    {
        try {
            $request->validate([
                'quantidade' => 'required|integer|not_in:0',
                'motivo'     => 'nullable|string|max:200',
            ]);

            $variant = $this->findVariant($request, $variantId);

            DB::transaction(function () use ($request, $variant) {
                $anterior   = $variant->estoque;
                $quantidade = $request->quantidade;
                $novo       = max(0, $anterior + $quantidade);

                $variant->update(['estoque' => $novo]);

                StockMovement::create([
                    'product_variant_id' => $variant->id,
                    'tenant_id'          => $request->user()->tenant_id, // ← direto do usuário
                    'user_id'            => $request->user()->id,
                    'tipo'               => $quantidade > 0 ? 'entrada' : 'saida',
                    'quantidade'         => $quantidade,
                    'estoque_anterior'   => $anterior,
                    'estoque_posterior'  => $novo,
                    'motivo'             => $request->motivo ?? 'Ajuste manual',
                ]);
            });

            $variant->refresh();
            return response()->json([
                'message' => 'Estoque ajustado com sucesso!',
                'estoque' => $variant->estoque,
                'alerta'  => $variant->estaBaixoDoMinimo(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza o estoque mínimo de uma variante.
     * Body: { estoque_minimo: 5 }
     */
    public function atualizarMinimo(Request $request, $variantId)
    {
        try {
            $request->validate(['estoque_minimo' => 'required|integer|min:0']);
            $variant = $this->findVariant($request, $variantId);
            $variant->update(['estoque_minimo' => $request->estoque_minimo]);

            return response()->json([
                'message'        => 'Mínimo atualizado!',
                'estoque_minimo' => $variant->estoque_minimo,
                'alerta'         => $variant->estaBaixoDoMinimo(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Histórico de movimentações ────────────────────────────────────────────

    public function historico(Request $request, $variantId)
    {
        try {
            $variant = $this->findVariant($request, $variantId);

            $movimentos = StockMovement::where('product_variant_id', $variant->id)
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get();

            return response()->json($movimentos, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Visão geral do estoque ────────────────────────────────────────────────

    public function visaoGeral(Request $request)
    {
        try {
            $query = Product::with(['variants', 'category'])
                ->where('ativo', true);

            if (!$request->user()->isAdminGlobal()) {
                $query->where('tenant_id', $request->user()->tenant_id);
            }

            $produtos = $query->orderBy('nome')->get();

            return response()->json($produtos->map(fn($p) => [
                'id'       => $p->id,
                'nome'     => $p->nome,
                'categoria' => $p->category->nome ?? '—',
                'variantes' => $p->variants->map(fn($v) => [
                    'id'             => $v->id,
                    'nome'           => $v->is_default ? 'Padrão' : $v->nome,
                    'estoque'        => $v->estoque,
                    'estoque_minimo' => $v->estoque_minimo,
                    'alerta'         => $v->estaBaixoDoMinimo(),
                    'zerado'         => $v->estaZerado(),
                ]),
            ]), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function findVariant(Request $request, $id): ProductVariant
    {
        $query = ProductVariant::whereHas('product', function ($q) use ($request) {
            if (!$request->user()->isAdminGlobal()) {
                $q->where('tenant_id', $request->user()->tenant_id);
            }
        })->where('id', $id);

        return $query->firstOrFail();
    }
}
