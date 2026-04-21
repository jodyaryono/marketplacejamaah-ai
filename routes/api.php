<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// ── WhatsApp Webhook ──────────────────────────────────────────────────────────
Route::post('/webhook/whacenter', [WebhookController::class, 'receive'])
    ->middleware(['throttle:120,1', 'webhook.auth'])
    ->name('webhook.whacenter');

// ── USYC Nanopayment API (Arc Blockchain) ─────────────────────────────────────
// Open endpoints (for AI agent and frontend demo)
Route::prefix('usyc')->group(function () {

    // Wallet
    Route::get('/wallet/{phone}',        [PaymentController::class, 'wallet']);
    Route::get('/balance/{phone}',       [PaymentController::class, 'balance']);
    Route::post('/wallet/topup-demo',    [PaymentController::class, 'topupDemo']);

    // Payments
    Route::post('/pay',                  [PaymentController::class, 'pay']);
    Route::post('/payment-confirmed',    [PaymentController::class, 'paymentConfirmed']);
    Route::post('/escrow/{tx}/release',  [PaymentController::class, 'releaseEscrow']);

    // Listings with USYC support
    Route::get('/listings',              [PaymentController::class, 'listings']);
    Route::patch('/listings/{listing}/enable', [PaymentController::class, 'enableUsyc']);

    // Transactions
    Route::get('/transactions/{phone}',  [PaymentController::class, 'transactions']);

    // Stats
    Route::get('/stats',                 [PaymentController::class, 'stats']);
});
