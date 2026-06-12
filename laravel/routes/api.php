<?php

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', function (Request $request) {
    $request->validate(['email' => 'required|email', 'password' => 'required']);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Credenciais inválidas'], 401);
    }

    return response()->json([
        'token' => $user->createToken('venda_boa_token')->plainTextToken
    ]);
});

Route::get('/teste', function () {
    return response()->json(['status' => 'ok']);
});


// Rotas do Fluxo de Caixa (PDV)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout/open', [CheckoutController::class, 'open_checkout']);
    Route::post('/checkout/launch', [CheckoutController::class, 'launchTransaction']);
    Route::post('/checkout/close', [CheckoutController::class, 'closeCheckout']);
    Route::get('/checkout/history', [CheckoutController::class, 'getHistory']);
    Route::post('/checkout/finalizar-venda', [CheckoutController::class, 'finalizeSale']);
    Route::get('/admin/caixas-abertos', [CheckoutController::class, 'getActiveCheckouts']);
    Route::post('/admin/caixas/{id}/fechar', [CheckoutController::class, 'forceCloseCheckout']);
    Route::get('/admin/historico-fechamentos', [CheckoutController::class, 'getClosingHistory']);
    // ── Catálogo (PDV — leitura) ──────────────────────────────────────────────
    Route::get('/catalogo', [ProductController::class, 'catalogo']);

    // ── Categorias (admin) ────────────────────────────────────────────────────
    Route::get('/categorias',        [ProductController::class, 'listarCategorias']);
    Route::post('/categorias',        [ProductController::class, 'criarCategoria']);
    Route::put('/categorias/{id}',   [ProductController::class, 'atualizarCategoria']);
    Route::delete('/categorias/{id}',   [ProductController::class, 'deletarCategoria']);

    // ── Produtos (admin) ──────────────────────────────────────────────────────
    Route::get('/produtos',          [ProductController::class, 'listarProdutos']);
    Route::post('/produtos',          [ProductController::class, 'criarProduto']);
    Route::put('/produtos/{id}',     [ProductController::class, 'atualizarProduto']);
    Route::delete('/produtos/{id}',     [ProductController::class, 'deletarProduto']);

    // ── Variantes (admin) ─────────────────────────────────────────────────────
    Route::post('/produtos/{id}/variantes',  [ProductController::class, 'criarVariante']);
    Route::delete('/variantes/{id}',           [ProductController::class, 'deletarVariante']);
});
