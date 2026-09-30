<?php

use App\Http\Controllers\Admin\Communication\DashboardController;
use App\Http\Controllers\Admin\Communication\DeliveryHistoryController;
use App\Http\Controllers\Admin\Communication\FailureController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('index');
Route::get('/history', [DeliveryHistoryController::class, 'index'])->name('history');
Route::get('/failures', [FailureController::class, 'index'])->name('failures');
