<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // ── Catálogo (leitura — usado pelo PDV) ──────────────────────────────────

    /**
     * Retorna todas as categorias ativas com seus produtos e variantes.
     * Usado na grade visual de venda.
     */
    public function catalogo()
    {
        try {
            $categorias = Category::where('ativo', true)
                ->with(['products.variants'])
                ->orderBy('nome')
                ->get();

            return response()->json($categorias, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Categorias ───────────────────────────────────────────────────────────

    public function listarCategorias()
    {
        return response()->json(Category::orderBy('nome')->get(), 200);
    }

    public function criarCategoria(Request $request)
    {
        try {
            $request->validate([
                'nome' => 'required|string|max:100',
                'cor'  => 'nullable|string|max:7',
            ]);

            $categoria = Category::create([
                'nome' => $request->nome,
                'cor'  => $request->cor ?? '#e8720c',
            ]);

            return response()->json($categoria, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function atualizarCategoria(Request $request, $id)
    {
        try {
            $categoria = Category::findOrFail($id);
            $request->validate([
                'nome' => 'sometimes|string|max:100',
                'cor'  => 'sometimes|string|max:7',
                'ativo'=> 'sometimes|boolean',
            ]);

            $categoria->update($request->only(['nome', 'cor', 'ativo']));

            return response()->json($categoria, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletarCategoria($id)
    {
        try {
            Category::findOrFail($id)->delete();
            return response()->json(['message' => 'Categoria removida.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Produtos ─────────────────────────────────────────────────────────────

    public function listarProdutos()
    {
        return response()->json(
            Product::with(['category', 'variants'])->orderBy('nome')->get(),
            200
        );
    }

    public function criarProduto(Request $request)
    {
        try {
            $request->validate([
                'category_id'   => 'required|exists:categories,id',
                'nome'          => 'required|string|max:100',
                'preco'         => 'required|numeric|min:0.01',
                'descricao'     => 'nullable|string',
                'tem_variantes' => 'boolean',
                'variantes'     => 'nullable|array',
                'variantes.*.nome'  => 'required_with:variantes|string',
                'variantes.*.preco' => 'required_with:variantes|numeric|min:0.01',
            ]);

            $produto = Product::create([
                'category_id'   => $request->category_id,
                'nome'          => $request->nome,
                'preco'         => $request->preco,
                'descricao'     => $request->descricao,
                'tem_variantes' => $request->boolean('tem_variantes', false),
            ]);

            // Cria as variantes se enviadas
            if ($request->tem_variantes && $request->variantes) {
                foreach ($request->variantes as $v) {
                    ProductVariant::create([
                        'product_id' => $produto->id,
                        'nome'       => $v['nome'],
                        'preco'      => $v['preco'],
                    ]);
                }
            }

            return response()->json($produto->load('variants'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function atualizarProduto(Request $request, $id)
    {
        try {
            $produto = Product::findOrFail($id);
            $request->validate([
                'category_id'   => 'sometimes|exists:categories,id',
                'nome'          => 'sometimes|string|max:100',
                'preco'         => 'sometimes|numeric|min:0.01',
                'descricao'     => 'nullable|string',
                'tem_variantes' => 'sometimes|boolean',
                'ativo'         => 'sometimes|boolean',
            ]);

            $produto->update($request->only([
                'category_id', 'nome', 'preco', 'descricao', 'tem_variantes', 'ativo'
            ]));

            return response()->json($produto->load('variants'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletarProduto($id)
    {
        try {
            Product::findOrFail($id)->delete();
            return response()->json(['message' => 'Produto removido.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Variantes ────────────────────────────────────────────────────────────

    public function criarVariante(Request $request, $productId)
    {
        try {
            $produto = Product::findOrFail($productId);
            $request->validate([
                'nome'  => 'required|string',
                'preco' => 'required|numeric|min:0.01',
            ]);

            $variante = ProductVariant::create([
                'product_id' => $produto->id,
                'nome'       => $request->nome,
                'preco'      => $request->preco,
            ]);

            // Marca o produto como "tem variantes"
            $produto->update(['tem_variantes' => true]);

            return response()->json($variante, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletarVariante($id)
    {
        try {
            ProductVariant::findOrFail($id)->delete();
            return response()->json(['message' => 'Variante removida.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}