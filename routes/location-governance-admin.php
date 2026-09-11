<?php

use App\Http\Controllers\Admin\LocationGovernanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LocationGovernanceController::class, 'index'])->name('index');
Route::post('/proposals/{locationProposal}/approve', [LocationGovernanceController::class, 'approve'])->name('proposals.approve');
Route::post('/proposals/{locationProposal}/reject', [LocationGovernanceController::class, 'reject'])->name('proposals.reject');
Route::post('/proposals/{locationProposal}/merge', [LocationGovernanceController::class, 'merge'])->name('proposals.merge');
Route::post('/proposals/{locationProposal}/request-evidence', [LocationGovernanceController::class, 'requestEvidence'])->name('proposals.request-evidence');
