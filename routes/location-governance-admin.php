<?php

use App\Http\Controllers\Admin\LocationGovernanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LocationGovernanceController::class, 'index'])->name('index');
Route::put('/settings', [LocationGovernanceController::class, 'updateSettings'])->name('settings.update');
Route::put('/proposals/{locationProposal}', [LocationGovernanceController::class, 'update'])->name('proposals.update');
Route::post('/proposals/{locationProposal}/approve', [LocationGovernanceController::class, 'approve'])->name('proposals.approve');
Route::post('/proposals/{locationProposal}/reject', [LocationGovernanceController::class, 'reject'])->name('proposals.reject');
Route::post('/proposals/{locationProposal}/merge', [LocationGovernanceController::class, 'merge'])->name('proposals.merge');
Route::post('/proposals/{locationProposal}/request-evidence', [LocationGovernanceController::class, 'requestEvidence'])->name('proposals.request-evidence');

Route::post('/structure-claims/{locationStructureClaim}/approve', [LocationGovernanceController::class, 'approveStructureClaim'])->name('structure-claims.approve');
Route::post('/structure-claims/{locationStructureClaim}/reject', [LocationGovernanceController::class, 'rejectStructureClaim'])->name('structure-claims.reject');
Route::post('/structure-claims/{locationStructureClaim}/request-evidence', [LocationGovernanceController::class, 'requestStructureClaimEvidence'])->name('structure-claims.request-evidence');

Route::post('/reference-settlements/{referenceSettlement}/review', [LocationGovernanceController::class, 'reviewSettlement'])->name('reference-settlements.review');
