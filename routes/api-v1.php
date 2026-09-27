<?php

use App\Http\Controllers\API\V1\Auth\NativeSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.v1.context', 'api.v1.envelope'])->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'api_version' => 'v1',
        ]);
    })->name('health');

    Route::post('/auth/session', [NativeSessionController::class, 'store'])
        ->name('auth.session.store');

    Route::middleware(['auth:sanctum', 'api.v1.device'])->group(function () {
        Route::get('/auth/session', [NativeSessionController::class, 'show'])
            ->name('auth.session.show');
        Route::post('/auth/session/rotate', [NativeSessionController::class, 'rotate'])
            ->name('auth.session.rotate');
        Route::delete('/auth/session', [NativeSessionController::class, 'destroy'])
            ->name('auth.session.destroy');
    });
});
