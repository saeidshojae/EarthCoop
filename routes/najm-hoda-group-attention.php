<?php

use App\Http\Controllers\Group\NajmHodaAttentionController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/groups/{group}/najm-hoda/attention', [NajmHodaAttentionController::class, 'show'])
        ->name('groups.najm-hoda.attention');

    Route::put('/groups/{group}/najm-hoda/attention', [NajmHodaAttentionController::class, 'update'])
        ->name('groups.najm-hoda.attention.update');
});
