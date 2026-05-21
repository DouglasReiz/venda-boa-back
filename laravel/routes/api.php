<?php

use App\Http\Controllers\Api\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Rotas do Fluxo de Caixa (PDV)
Route::prefix('checkout')->group(function () {
    Route::post('/open', [CheckoutController::class, 'open_checkout']);
    Route::post('/launch', [CheckoutController::class, 'launchTransaction']);
    Route::post('/close', [CheckoutController::class, 'closeCheckout']);
});
