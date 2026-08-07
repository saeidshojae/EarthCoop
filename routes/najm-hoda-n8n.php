<?php

use App\Http\Controllers\NajmHoda\N8nCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/najm-hoda/n8n/callback', N8nCallbackController::class)
    ->middleware('throttle:najm-hoda-n8n-callback')
    ->name('najm-hoda.n8n.callback');
