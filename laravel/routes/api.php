<?php
/**
 * Venda Boa PDV
 * 
 * @copyright Copyright (c) 2026 Douglas Alves
 * @license PROPRIETÁRIA - TODOS OS DIREITOS RESERVADOS.
 * É estritamente proibido copiar, modificar ou distribuir este arquivo 
 * sem autorização expressa por escrito do autor.
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

    Route::get('/user', fn(\Illuminate\Http\Request $r) => $r->user()->load('tenant'));
    Route::post('/usuario/trocar-senha', [UserController::class, 'trocarSenha']);

    // Checkout — todos os usuários autenticados
    Route::post('/checkout/open',            [CheckoutController::class, 'open_checkout']);
    Route::post('/checkout/close',           [CheckoutController::class, 'closeCheckout']);
    Route::post('/checkout/launch',          [CheckoutController::class, 'launchTransaction']);
    Route::post('/checkout/finalizar-venda', [CheckoutController::class, 'finalizeSale']);
    Route::get('/checkout/history',         [CheckoutController::class, 'getHistory']);

    // Catálogo PDV — todos
    Route::get('/catalogo', [ProductController::class, 'catalogo']);

    // Admin: caixas e gráfico
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/caixas-abertos',        [CheckoutController::class, 'getActiveCheckouts']);
        Route::post('/admin/caixas/{id}/fechar',    [CheckoutController::class, 'forceCloseCheckout']);
        Route::get('/admin/historico-fechamentos', [CheckoutController::class, 'getClosingHistory']);

        Route::get('/categorias',              [ProductController::class, 'listarCategorias']);
        Route::post('/categorias',              [ProductController::class, 'criarCategoria']);
        Route::put('/categorias/{id}',         [ProductController::class, 'atualizarCategoria']);
        Route::delete('/categorias/{id}',         [ProductController::class, 'deletarCategoria']);

        Route::get('/produtos',                [ProductController::class, 'listarProdutos']);
        Route::post('/produtos',                [ProductController::class, 'criarProduto']);
        Route::put('/produtos/{id}',           [ProductController::class, 'atualizarProduto']);
        Route::delete('/produtos/{id}',           [ProductController::class, 'deletarProduto']);

        Route::post('/produtos/{id}/variantes', [ProductController::class, 'criarVariante']);
        Route::delete('/variantes/{id}',          [ProductController::class, 'deletarVariante']);
    });


    // ── Estoque ───────────────────────────────────────────────────────────────────

    // Alertas — disponível para todos (operador vê alertas do PDV)
    Route::get('/estoque/alertas', [StockController::class, 'alertas']);

    // Visão geral e ajustes — somente admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/estoque',                         [StockController::class, 'visaoGeral']);
        Route::post('/estoque/variantes/{id}/ajustar',  [StockController::class, 'ajustar']);
        Route::put('/estoque/variantes/{id}/minimo',   [StockController::class, 'atualizarMinimo']);
        Route::get('/estoque/variantes/{id}/historico', [StockController::class, 'historico']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/usuarios',           [UserController::class, 'index']);
        Route::post('/usuarios',           [UserController::class, 'store']);
        Route::put('/usuarios/{id}',      [UserController::class, 'update']);
        Route::patch('/usuarios/{id}/ativo', [UserController::class, 'toggleAtivo']);
    });

    // ── Empresas (tenants) — somente admin_global ───────────────────────────────────
    Route::middleware('role:admin_global')->group(function () {
        Route::get('/tenants',      [TenantController::class, 'index']);
        Route::post('/tenants',      [TenantController::class, 'store']);
        Route::put('/tenants/{id}', [TenantController::class, 'update']);
    });
});
