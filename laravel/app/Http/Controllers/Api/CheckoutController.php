<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function open_checkout(Request $request)
    {
        try {
            $request->validate([
                'valor_abertura' => 'required|numeric'
            ]);

            $checkout = Checkout::create([
                'user_id'        => $request->user()->id,
                'valor_abertura' => $request->valor_abertura,
                'status'         => 'aberto',
                'data_abertura'  => now()
            ]);

            return response()->json(['message' => 'Caixa aberto!', 'data' => $checkout], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao abrir caixa: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function launchTransaction(Request $request)
    {
        try {
            $checkoutAberto = Checkout::where('status', 'aberto')->first();
            if (!$checkoutAberto) {
                return response()->json(['error' => 'No open checkout found.'], 400);
            }

            $request->validate([
                'tipo'             => 'required',
                'metodo_pagamento' => 'required',
                'valor'            => 'required|numeric|min:0.01',
                'descricao'        => 'nullable|string'
            ]);

            // FIX: normaliza o método de pagamento para o formato aceito pelo ENUM
            // O frontend manda 'credito'/'debito'; o banco espera 'cartao_credito'/'cartao_debito'
            $metodo = $this->normalizarMetodoPagamento($request->metodo_pagamento);

            $transaction = Transaction::create([
                'checkout_id'      => $checkoutAberto->id,
                'tipo'             => $request->tipo,
                'metodo_pagamento' => $metodo,
                'valor'            => $request->valor,
                'descricao'        => $request->descricao,
                // FIX: 'pdv' não existe no ENUM — valor padrão correto é 'balcao'
                'origem'           => $request->origem ?? 'balcao',
            ]);

            return response()->json(['message' => 'Transaction registered!', 'transaction' => $transaction], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function closeCheckout(Request $request)
    {
        try {
            $checkout = Checkout::where('status', 'aberto')->first();

            if (!$checkout) {
                return response()->json(['error' => 'No active checkout found to close.'], 404);
            }

            $resumo = Transaction::where('checkout_id', $checkout->id)
                ->selectRaw("
                    SUM(CASE WHEN tipo IN ('entrada', 'suprimento') THEN valor ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
                ")
                ->first();

            $entradas     = (float)($resumo->total_entradas ?? 0);
            $saidas       = (float)($resumo->total_saidas ?? 0);
            $finalBalance = $checkout->valor_abertura + $entradas - $saidas;

            $checkout->update([
                'status'           => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento'  => now()
            ]);

            return response()->json([
                'message' => 'Checkout closed successfully!',
                'summary' => [
                    'initial_cash'  => (float)$checkout->valor_abertura,
                    'total_in'      => (float)$entradas,
                    'total_out'     => (float)$saidas,
                    'final_balance' => (float)$finalBalance
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function finalizeSale(Request $request)
    {
        try {
            $request->validate([
                'valor'            => 'required|numeric|min:0.01',
                'metodo_pagamento' => 'required|string'
            ]);

            $checkoutAberto = Checkout::where('status', 'aberto')->first();
            if (!$checkoutAberto) {
                return response()->json(['error' => 'Nenhum caixa aberto encontrado.'], 400);
            }

            // FIX: normaliza o método de pagamento para o ENUM do banco
            $metodo = $this->normalizarMetodoPagamento($request->metodo_pagamento);

            $transaction = Transaction::create([
                'checkout_id'      => $checkoutAberto->id,
                'tipo'             => 'entrada',
                'metodo_pagamento' => $metodo,
                'valor'            => $request->valor,
                'descricao'        => 'Venda finalizada via ' . $metodo,
                // FIX: 'pdv' não existe no ENUM — valor correto é 'balcao'
                'origem'           => 'balcao',
            ]);

            return response()->json([
                'message'     => 'Venda finalizada com sucesso!',
                'transaction' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        try {
            $checkout = Checkout::where('user_id', $request->user()->id)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json([], 200);
            }

            $transactions = Transaction::where('checkout_id', $checkout->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($transactions, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar histórico'], 500);
        }
    }

    public function getActiveCheckouts()
    {
        try {
            $caixas = Checkout::where('status', 'aberto')
                ->with('user')
                ->get();

            return response()->json($caixas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar caixas abertos: ' . $e->getMessage()], 500);
        }
    }

    public function forceCloseCheckout($id)
    {
        try {
            $checkout = Checkout::where('id', $id)->where('status', 'aberto')->first();

            if (!$checkout) {
                return response()->json(['error' => 'Caixa não encontrado ou já fechado.'], 404);
            }

            $resumo = Transaction::where('checkout_id', $checkout->id)
                ->selectRaw("
                    SUM(CASE WHEN tipo IN ('entrada', 'suprimento') THEN valor ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
                ")
                ->first();

            $entradas     = (float)($resumo->total_entradas ?? 0);
            $saidas       = (float)($resumo->total_saidas ?? 0);
            $finalBalance = $checkout->valor_abertura + $entradas - $saidas;

            $checkout->update([
                'status'           => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento'  => now()
            ]);

            return response()->json(['message' => 'Caixa fechado com sucesso pelo administrador!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao forçar fechamento: ' . $e->getMessage()], 500);
        }
    }

    public function getClosingHistory()
    {
        try {
            $fechamentos = Checkout::where('status', 'fechado')
                ->orderBy('data_fechamento', 'desc')
                ->take(7)
                ->get()
                ->reverse();

            $dadosFormatados = $fechamentos->map(function ($checkout) {
                return [
                    'label' => \Carbon\Carbon::parse($checkout->data_fechamento)->format('d/m H:i'),
                    'valor' => (float)$checkout->valor_fechamento
                ];
            });

            return response()->json($dadosFormatados->values(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao gerar dados do gráfico: ' . $e->getMessage()], 500);
        }
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    /**
     * Normaliza o valor vindo do frontend para o ENUM do banco.
     *
     * Frontend envia:  'dinheiro' | 'credito' | 'debito' | 'pix'
     * ENUM do banco:   'dinheiro' | 'cartao_credito' | 'cartao_debito' | 'pix' | 'outros'
     */
    private function normalizarMetodoPagamento(string $metodo): string
    {
        return match ($metodo) {
            'credito'       => 'cartao_credito',
            'debito'        => 'cartao_debito',
            'cartao_credito',
            'cartao_debito',
            'dinheiro',
            'pix'           => $metodo,
            default         => 'outros',
        };
    }
}