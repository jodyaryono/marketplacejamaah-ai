<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whacenter', [WebhookController::class, 'receive'])
    ->middleware(['throttle:120,1', 'webhook.auth'])
    ->name('webhook.whacenter');
