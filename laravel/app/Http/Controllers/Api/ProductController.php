<?php
// app/Http/Controllers/Api/ProductController.php
// Substitui o arquivo anterior integralmente

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // ── Catálogo PDV ─────────────────────────────────────────────────────────

    public function catalogo(Request $request)
    {
        try {
            $query = Category::where('ativo', true)
                ->with(['products' => function ($q) use ($request) {
                    $q->where('ativo', true)
                        ->with(['variants' => fn($v) => $v->where('ativo', true)]);
                    if (!$request->user()->isAdminGlobal()) {
                        $q->where('tenant_id', $request->user()->tenant_id);
                    }
                }])
                ->orderBy('nome');

            if (!$request->user()->isAdminGlobal()) {
                $query->where('tenant_id', $request->user()->tenant_id);
            }

            // Inclui info de estoque no catálogo para o PDV poder avisar
            $categorias = $query->get()->map(function ($cat) {
                $cat->products = $cat->products->map(function ($p) {
                    $p->variants = $p->variants->map(fn($v) => array_merge($v->toArray(), [
                        'sem_estoque' => $v->estaZerado(),
                        'estoque_baixo' => $v->estaBaixoDoMinimo(),
                    ]));
                    return $p;
                });
                return $cat;
            });

            return response()->json($categorias, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Categorias ────────────────────────────────────────────────────────────

    public function listarCategorias(Request $request)
    {
        $query = Category::orderBy('nome');
        if (!$request->user()->isAdminGlobal()) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }
        return response()->json($query->get(), 200);
    }

    public function criarCategoria(Request $request)
    {
        try {
            $request->validate(['nome' => 'required|string|max:100', 'cor' => 'nullable|string|max:7']);
            $categoria = Category::create([
                'tenant_id' => $request->user()->tenant_id,
                'nome'      => $request->nome,
                'cor'       => $request->cor ?? '#48bb78',
            ]);
            return response()->json($categoria, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function atualizarCategoria(Request $request, $id)
    {
        try {
            $cat = $this->findCategoria($request, $id);
            $cat->update($request->only(['nome', 'cor', 'ativo']));
            return response()->json($cat, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletarCategoria(Request $request, $id)
    {
        try {
            $this->findCategoria($request, $id)->delete();
            return response()->json(['message' => 'Categoria removida.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Produtos ──────────────────────────────────────────────────────────────

    public function listarProdutos(Request $request)
    {
        $query = Product::with(['category', 'variants'])->orderBy('nome');
        if (!$request->user()->isAdminGlobal()) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }
        return response()->json($query->get(), 200);
    }

    public function criarProduto(Request $request)
    {
        try {
            $request->validate([
                'category_id'           => 'required|exists:categories,id',
                'tenant_id'             => $request->user()->tenant_id,
                'nome'                  => 'required|string|max:100',
                'preco'                 => 'required|numeric|min:0.01',
                'descricao'             => 'nullable|string',
                'tem_variantes'         => 'boolean',
                // Campos de estoque para produto simples
                'estoque'               => 'integer|min:0',
                'estoque_minimo'        => 'integer|min:0',
                // Variantes
                'variantes'             => 'nullable|array',
                'variantes.*.nome'      => 'required_with:variantes|string',
                'variantes.*.preco'     => 'required_with:variantes|numeric|min:0.01',
                'variantes.*.estoque'   => 'integer|min:0',
                'variantes.*.estoque_minimo' => 'integer|min:0',
            ]);

            /** @var Product $produto */
            $produto = new Product();

            DB::transaction(function () use ($request, &$produto) {
                $temVariantes = $request->boolean('tem_variantes', false);

                /** @var Product $produto */
                $produto = Product::create([
                    'tenant_id'     => $request->user()->tenant_id,
                    'category_id'   => $request->category_id,
                    'nome'          => $request->nome,
                    'preco'         => $request->preco,
                    'descricao'     => $request->descricao,
                    'tem_variantes' => $temVariantes,
                ]);

                if ($temVariantes && $request->variantes) {
                    // Produto com variantes reais
                    foreach ($request->variantes as $v) {
                        ProductVariant::create([
                            'product_id'     => $produto->id,
                            'nome'           => $v['nome'],
                            'preco'          => $v['preco'],
                            'estoque'        => $v['estoque'] ?? 0,
                            'estoque_minimo' => $v['estoque_minimo'] ?? 5,
                            'is_default'     => false,
                        ]);
                    }
                } else {
                    // Produto simples: cria variante "Padrão" automaticamente
                    ProductVariant::create([
                        'product_id'     => $produto->id,
                        'nome'           => 'Padrão',
                        'preco'          => $request->preco,
                        'estoque'        => $request->estoque ?? 0,
                        'estoque_minimo' => $request->estoque_minimo ?? 5,
                        'is_default'     => true,
                    ]);
                }
            });

            return response()->json($produto->load('variants'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function atualizarProduto(Request $request, $id)
    {
        try {
            $produto = $this->findProduto($request, $id);
            $produto->update($request->only([
                'category_id',
                'nome',
                'preco',
                'descricao',
                'tem_variantes',
                'ativo'
            ]));
            return response()->json($produto->load('variants'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deletarProduto(Request $request, $id)
    {
        try {
            $this->findProduto($request, $id)->delete();
            return response()->json(['message' => 'Produto removido.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Variantes ─────────────────────────────────────────────────────────────

    public function criarVariante(Request $request, $productId)
    {
        try {
            $produto = $this->findProduto($request, $productId);
            $request->validate([
                'nome'           => 'required|string',
                'preco'          => 'required|numeric|min:0.01',
                'estoque'        => 'integer|min:0',
                'estoque_minimo' => 'integer|min:0',
            ]);

            // Se tinha variante padrão, remove ela ao adicionar variantes reais
            $produto->variants()->where('is_default', true)->delete();

            $variante = ProductVariant::create([
                'product_id'     => $produto->id,
                'nome'           => $request->nome,
                'preco'          => $request->preco,
                'estoque'        => $request->estoque ?? 0,
                'estoque_minimo' => $request->estoque_minimo ?? 5,
                'is_default'     => false,
            ]);

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

    // ── Privados ──────────────────────────────────────────────────────────────

    private function findCategoria(Request $request, $id): Category
    {
        $q = Category::where('id', $id);
        if (!$request->user()->isAdminGlobal()) {
            $q->where('tenant_id', $request->user()->tenant_id);
        }
        return $q->firstOrFail();
    }

    private function findProduto(Request $request, $id): Product
    {
        $q = Product::where('id', $id);
        if (!$request->user()->isAdminGlobal()) {
            $q->where('tenant_id', $request->user()->tenant_id);
        }
        return $q->firstOrFail();
    }
}
