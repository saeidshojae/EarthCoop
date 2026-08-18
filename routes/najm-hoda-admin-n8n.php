<?php

use App\Http\Controllers\Admin\NajmHodaN8nController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:najm-hoda.view-dashboard')->group(function (): void {
    Route::get('/', [NajmHodaN8nController::class, 'index'])->name('index');
    Route::post('/health', [NajmHodaN8nController::class, 'health'])
        ->middleware('throttle:najm-hoda-autonomy-read')
        ->name('health');
});

Route::middleware(['permission:najm-hoda.manage-settings', 'throttle:najm-hoda-autonomy-write'])->group(function (): void {
    Route::post('/controls', [NajmHodaN8nController::class, 'updateControls'])->name('controls.update');
    Route::post('/secret-rotation/verify', [NajmHodaN8nController::class, 'markSecretRotation'])->name('secret-rotation.verify');
});
