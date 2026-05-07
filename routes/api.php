<?php

use App\Http\Controllers\Claw3dController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whacenter', [WebhookController::class, 'receive'])
    ->middleware(['throttle:120,1', 'webhook.auth'])
    ->name('webhook.whacenter');

Route::prefix('claw3d')->group(function () {
    Route::get('/health', [Claw3dController::class, 'health']);
    Route::get('/state', [Claw3dController::class, 'state']);
    Route::get('/registry', [Claw3dController::class, 'registry']);
    Route::get('/activity', [Claw3dController::class, 'activity']);
    Route::post('/simulate', [Claw3dController::class, 'simulate']);
    Route::post('/v1/chat/completions', [Claw3dController::class, 'chatCompletions']);
});
