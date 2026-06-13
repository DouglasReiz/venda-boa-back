<?php
// app/Http/Controllers/Api/CheckoutController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    // ── Helper: escopo de tenant ──────────────────────────────────────────────

    /**
     * Retorna a query de Checkout já filtrada pelo tenant do usuário.
     * Admin global vê tudo. Admin vê o tenant. Operador vê só o próprio.
     */
    private function checkoutQuery(Request $request, bool $apenasProprioUser = false)
    {
        $user  = $request->user();
        $query = Checkout::query();

        if ($user->isAdminGlobal()) {
            // vê tudo — sem filtro
        } elseif ($user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        } else {
            // operador: só o próprio caixa
            $query->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id);
        }

        if ($apenasProprioUser) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    // ── Abrir caixa ───────────────────────────────────────────────────────────

    public function open_checkout(Request $request)
    {
        try {
            $request->validate(['valor_abertura' => 'required|numeric']);

            $checkout = Checkout::create([
                'tenant_id'      => $request->user()->tenant_id,
                'user_id'        => $request->user()->id,
                'valor_abertura' => $request->valor_abertura,
                'status'         => 'aberto',
                'data_abertura'  => now(),
            ]);

            return response()->json([
                'message' => 'Caixa aberto!',
                'data'    => $checkout->load('user'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao abrir caixa: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Lançar movimentação ───────────────────────────────────────────────────

    public function launchTransaction(Request $request)
    {
        try {
            // Busca o caixa aberto do próprio usuário
            $checkout = $this->checkoutQuery($request, true)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json(['error' => 'Nenhum caixa aberto encontrado.'], 400);
            }

            $request->validate([
                'tipo'             => 'required',
                'metodo_pagamento' => 'required',
                'valor'            => 'required|numeric|min:0.01',
                'descricao'        => 'nullable|string',
            ]);

            $transaction = Transaction::create([
                'checkout_id'      => $checkout->id,
                'tipo'             => $request->tipo,
                'metodo_pagamento' => $this->normalizarMetodo($request->metodo_pagamento),
                'valor'            => $request->valor,
                'descricao'        => $request->descricao,
                'origem'           => $request->origem ?? 'balcao',
            ]);

            return response()->json([
                'message'     => 'Lançamento registrado!',
                'transaction' => $transaction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Fechar caixa ──────────────────────────────────────────────────────────

    public function closeCheckout(Request $request)
    {
        try {
            $checkout = $this->checkoutQuery($request, true)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json(['error' => 'Nenhum caixa aberto.'], 404);
            }

            [$entradas, $saidas, $finalBalance] = $this->calcularSaldo($checkout);

            $checkout->update([
                'status'           => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento'  => now(),
            ]);

            return response()->json([
                'message' => 'Caixa fechado com sucesso!',
                'summary' => [
                    'operador'      => $checkout->user->name,
                    'initial_cash'  => (float) $checkout->valor_abertura,
                    'total_in'      => (float) $entradas,
                    'total_out'     => (float) $saidas,
                    'final_balance' => (float) $finalBalance,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Finalizar venda ───────────────────────────────────────────────────────

    public function finalizeSale(Request $request)
    {
        try {
            $request->validate([
                'valor'                    => 'required|numeric|min:0.01',
                'metodo_pagamento'         => 'required|string',
                'itens'                    => 'nullable|array',
                'itens.*.variant_id'       => 'required_with:itens|exists:product_variants,id',
                'itens.*.quantidade'       => 'required_with:itens|integer|min:1',
                'itens.*.nome'             => 'nullable|string',
            ]);

            $checkout = Checkout::where('user_id', $request->user()->id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json(['error' => 'Nenhum caixa aberto encontrado.'], 400);
            }

            $metodo = $this->normalizarMetodo($request->metodo_pagamento);

            DB::transaction(function () use ($request, $checkout, $metodo) {
                // 1. Registra a transação financeira
                $transaction = Transaction::create([
                    'checkout_id'      => $checkout->id,
                    'tipo'             => 'entrada',
                    'metodo_pagamento' => $metodo,
                    'valor'            => $request->valor,
                    'descricao'        => 'Venda finalizada via ' . $metodo,
                    'origem'           => 'balcao',
                ]);

                // 2. Baixa o estoque de cada item vendido
                if ($request->itens) {
                    foreach ($request->itens as $item) {
                        $variant = \App\Models\ProductVariant::findOrFail($item['variant_id']);
                        $variant->baixar(
                            $item['quantidade'],
                            $transaction->id,
                            $request->user()->id,
                            $request->user()->tenant_id  // ← adicione este parâmetro
                        );
                    }
                }
            });

            // 3. Verifica alertas de estoque após a venda
            $alertas = \App\Models\ProductVariant::whereHas(
                'product',
                fn($q) =>
                $q->where('tenant_id', $request->user()->tenant_id)->where('ativo', true)
            )
                ->where('ativo', true)
                ->whereColumn('estoque', '<=', 'estoque_minimo')
                ->count();

            return response()->json([
                'message'        => 'Venda finalizada com sucesso!',
                'alertas_estoque' => $alertas, // frontend pode exibir badge de alerta
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Histórico ─────────────────────────────────────────────────────────────

    public function getHistory(Request $request)
    {
        try {
            $checkout = $this->checkoutQuery($request, true)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) return response()->json([], 200);

            $transactions = Transaction::where('checkout_id', $checkout->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($transactions, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar histórico.'], 500);
        }
    }

    // ── Admin: caixas abertos ─────────────────────────────────────────────────

    public function getActiveCheckouts(Request $request)
    {
        try {
            // Admin vê os do tenant; admin_global vê todos
            $caixas = $this->checkoutQuery($request)
                ->where('status', 'aberto')
                ->with('user:id,name,email')
                ->get();

            return response()->json($caixas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Admin: forçar fechamento ──────────────────────────────────────────────

    public function forceCloseCheckout(Request $request, $id)
    {
        try {
            $checkout = $this->checkoutQuery($request)
                ->where('id', $id)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json(['error' => 'Caixa não encontrado ou já fechado.'], 404);
            }

            [$entradas, $saidas, $finalBalance] = $this->calcularSaldo($checkout);

            $checkout->update([
                'status'           => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento'  => now(),
            ]);

            return response()->json(['message' => 'Caixa fechado pelo administrador!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Histórico de fechamentos (gráfico) ────────────────────────────────────

    public function getClosingHistory(Request $request)
    {
        try {
            $fechamentos = $this->checkoutQuery($request)
                ->where('status', 'fechado')
                ->orderBy('data_fechamento', 'desc')
                ->take(7)
                ->get()
                ->reverse();

            $dados = $fechamentos->map(fn($c) => [
                'label'   => \Carbon\Carbon::parse($c->data_fechamento)->format('d/m H:i'),
                'valor'   => (float) $c->valor_fechamento,
                'operador' => $c->user->name ?? '—',
            ]);

            return response()->json($dados->values(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function calcularSaldo(Checkout $checkout): array
    {
        $resumo = Transaction::where('checkout_id', $checkout->id)
            ->selectRaw("
                SUM(CASE WHEN tipo IN ('entrada','suprimento') THEN valor ELSE 0 END) as total_entradas,
                SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
            ")
            ->first();

        $entradas     = (float) ($resumo->total_entradas ?? 0);
        $saidas       = (float) ($resumo->total_saidas   ?? 0);
        $finalBalance = $checkout->valor_abertura + $entradas - $saidas;

        return [$entradas, $saidas, $finalBalance];
    }

    private function normalizarMetodo(string $metodo): string
    {
        return match ($metodo) {
            'credito' => 'cartao_credito',
            'debito'  => 'cartao_debito',
            'cartao_credito', 'cartao_debito', 'dinheiro', 'pix' => $metodo,
            default   => 'outros',
        };
    }
}
