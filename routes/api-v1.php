<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api.v1.context', 'api.v1.envelope'])->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'api_version' => 'v1',
        ]);
    })->name('health');
});
