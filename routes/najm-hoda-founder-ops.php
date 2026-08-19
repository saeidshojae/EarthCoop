<?php

use App\Http\Controllers\Admin\FounderOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:najm-hoda-autonomy-read'])
    ->prefix('admin/najm-hoda/founder-ops')
    ->name('admin.najm-hoda.founder-ops.')
    ->group(function (): void {
        Route::get('/brief', [FounderOperationsController::class, 'brief'])->name('brief');
        Route::get('/snapshot', [FounderOperationsController::class, 'snapshot'])->name('snapshot');
        Route::get('/approvals', [FounderOperationsController::class, 'approvals'])->name('approvals');
        Route::get('/authority', [FounderOperationsController::class, 'authority'])->name('authority');
    });
