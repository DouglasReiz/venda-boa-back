<?php

use App\Http\Controllers\Api\CheckoutController;
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
});
