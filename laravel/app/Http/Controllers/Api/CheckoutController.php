<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Transaction;
use Illuminate\Http\Request;
use \Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function open_checkout(Request $request)
    {
        try {
            // Validação básica
            $request->validate([
                'valor_abertura' => 'required|numeric'
            ]);

            // Tenta criar
            $checkout = Checkout::create([
                'user_id' => $request->user()->id, // <--- Isso garante segurança total
                'valor_abertura' => $request->valor_abertura,
                'status' => 'aberto',
                'data_abertura' => now()
            ]);

            return response()->json(['message' => 'Caixa aberto!', 'data' => $checkout], 201);
        } catch (\Exception $e) {
            // ISSO VAI ESCREVER O ERRO REAL NO LOG QUANDO DER 500
            Log::error('Erro ao abrir caixa: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function launchTransaction(Request $request)
    {
        try {
            // 1. Alinhe o status: se você gravou como 'aberto', busque por 'aberto'
            $checkoutAberto = Checkout::where('status', 'aberto')->first();
            if (!$checkoutAberto) {
                return response()->json(['error' => 'No open checkout found.'], 400);
            }

            $request->validate([
                'tipo' => 'required',
                'metodo_pagamento' => 'required',
                'valor' => 'required|numeric|min:0.01',
                'descricao' => 'nullable|string'
            ]);

            // Linha 51 (Onde o erro acontece)
            $transaction = Transaction::create([
                'checkout_id' => $checkoutAberto->id, // Verifique se na migration está 'checkout_id' ou 'caixa_id'
                'tipo' => $request->tipo,
                'metodo_pagamento' => $request->metodo_pagamento,
                'valor' => $request->valor,
                'descricao' => $request->descricao,
                'origem' => $request->origem ?? 'balcao'
            ]);

            return response()->json(['message' => 'Transaction registered!', 'transaction' => $transaction], 201);
        } catch (\Exception $e) {
            // Esse retorno vai dizer o motivo exato do erro 500 direto no seu Postman
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function closeCheckout(Request $request)
    {
        try {
            // 1. Busca o checkout aberto
            $checkout = Checkout::where('status', 'aberto')->first();

            if (!$checkout) {
                return response()->json(['error' => 'No active checkout found to close.'], 404);
            }

            // 2. Calcula as somas
            // Nota: Certifique-se de que 'tipo' usa exatamente as strings 'entrada', 'saida', 'suprimento'
            // Substitua o bloco de cálculo por este:
            $resumo = Transaction::where('checkout_id', $checkout->id)
                ->selectRaw("
                    SUM(CASE WHEN tipo IN ('entrada', 'suprimento') THEN valor ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
                ")
                ->first();

            $entradas = (float)($resumo->total_entradas ?? 0);
            $saidas = (float)($resumo->total_saidas ?? 0);
            $finalBalance = $checkout->valor_abertura + $entradas - $saidas;

            // 3. Atualiza o registro
            $checkout->update([
                'status' => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento' => now()
            ]);

            return response()->json([
                'message' => 'Checkout closed successfully!',
                'summary' => [
                    'initial_cash' => (float)$checkout->valor_abertura,
                    'total_in' => (float)$entradas,
                    'total_out' => (float)$saidas,
                    'final_balance' => (float)$finalBalance
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
