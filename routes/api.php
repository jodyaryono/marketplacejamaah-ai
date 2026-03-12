<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whacenter', [WebhookController::class, 'receive'])
    ->middleware('webhook.auth')
    ->name('webhook.whacenter');
