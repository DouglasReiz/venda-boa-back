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

    public function finalizeSale(Request $request)
    {
        try {
            $request->validate([
                'valor' => 'required|numeric|min:0.01',
                'metodo_pagamento' => 'required|string'
            ]);

            $checkoutAberto = Checkout::where('status', 'aberto')->first();
            if (!$checkoutAberto) {
                return response()->json(['error' => 'Nenhum caixa aberto encontrado.'], 400);
            }

            // Criando a transação de venda
            $transaction = Transaction::create([
                'checkout_id' => $checkoutAberto->id,
                'tipo' => 'entrada', // Venda é sempre entrada
                'metodo_pagamento' => $request->metodo_pagamento,
                'valor' => $request->valor,
                'descricao' => 'Venda finalizada via ' . $request->metodo_pagamento,
                'origem' => 'pdv'
            ]);

            return response()->json([
                'message' => 'Venda finalizada com sucesso!',
                'transaction' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        try {
            // Busca o checkout aberto do usuário atual
            $checkout = Checkout::where('user_id', $request->user()->id)
                ->where('status', 'aberto')
                ->first();

            if (!$checkout) {
                return response()->json([], 200); // Retorna vazio se não houver caixa
            }

            // Busca as transações deste checkout
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
            // Busca todos os caixas com status 'aberto' e traz junto os dados do operador (user)
            $caixas = Checkout::where('status', 'aberto')
                ->with('user') // Garante que o relacionamento traga o nome do operador
                ->get();

            return response()->json($caixas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar caixas abertos: ' . $e->getMessage()], 500);
        }
    }

    public function forceCloseCheckout($id)
    {
        try {
            // 1. Busca o caixa específico pelo ID enviado pelo admin
            $checkout = Checkout::where('id', $id)->where('status', 'aberto')->first();

            if (!$checkout) {
                return response()->json(['error' => 'Caixa não encontrado ou já fechado.'], 404);
            }

            // 2. Calcula o saldo atual dele baseado nas transações existentes
            $resumo = Transaction::where('checkout_id', $checkout->id)
                ->selectRaw("
                SUM(CASE WHEN tipo IN ('entrada', 'suprimento') THEN valor ELSE 0 END) as total_entradas,
                SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
            ")
                ->first();

            $entradas = (float)($resumo->total_entradas ?? 0);
            $saidas = (float)($resumo->total_saidas ?? 0);
            $finalBalance = $checkout->valor_abertura + $entradas - $saidas;

            // 3. Força o fechamento
            $checkout->update([
                'status' => 'fechado',
                'valor_fechamento' => $finalBalance,
                'data_fechamento' => now()
            ]);

            return response()->json(['message' => 'Caixa fechado com sucesso pelo administrador!'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao forçar fechamento: ' . $e->getMessage()], 500);
        }
    }

    public function getClosingHistory()
    {
        try {
            // Busca os últimos 7 caixas fechados
            $fechamentos = Checkout::where('status', 'fechado')
                ->orderBy('data_fechamento', 'desc')
                ->take(7)
                ->get()
                ->reverse(); // Inverte para ordem cronológica (esquerda para a direita no gráfico)

            // Formata os dados para o formato que o Chart.js espera
            $dadosFormatados = $fechamentos->map(function ($checkout) {
                return [
                    // Formato: "Dia/Mês Hora:Minuto" (Ex: 03/06 17:45)
                    'label' => \Carbon\Carbon::parse($checkout->data_fechamento)->format('d/m H:i'),
                    'valor' => (float)$checkout->valor_fechamento
                ];
            });

            return response()->json($dadosFormatados->values(), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao gerar dados do gráfico: ' . $e->getMessage()], 500);
        }
    }
}
