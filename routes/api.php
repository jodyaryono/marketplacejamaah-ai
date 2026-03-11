<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whacenter', [WebhookController::class, 'receive'])
    ->name('webhook.whacenter');
