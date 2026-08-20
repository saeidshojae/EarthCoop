<?php

use App\Http\Controllers\Admin\FounderOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:najm-hoda-autonomy-read'])->prefix('admin/najm-hoda/founder-ops')->name('admin.najm-hoda.founder-ops.')->group(function (): void {
    Route::get('/', [FounderOperationsController::class, 'index'])->name('index');
    Route::get('/brief', [FounderOperationsController::class, 'brief'])->name('brief');
    Route::get('/snapshot', [FounderOperationsController::class, 'snapshot'])->name('snapshot');
    Route::get('/autonomy-plan', [FounderOperationsController::class, 'autonomyPlan'])->name('autonomy-plan');
    Route::get('/approvals', [FounderOperationsController::class, 'approvals'])->name('approvals');
    Route::get('/authority', [FounderOperationsController::class, 'authority'])->name('authority');
});

Route::middleware(['throttle:najm-hoda-autonomy-write'])->prefix('admin/najm-hoda/founder-ops')->name('admin.najm-hoda.founder-ops.')->group(function (): void {
    Route::post('/support-drafts/{draft}/request-send', [FounderOperationsController::class, 'requestSupportDraftSend'])->name('support-drafts.request-send');
    Route::post('/support-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideSupportDraft'])->name('support-approvals.decision');
    Route::post('/reference/{type}/{id}/request-approve', [FounderOperationsController::class, 'requestReferenceApprove'])->name('reference.request-approve');
    Route::post('/reference-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideReferenceApproval'])->name('reference-approvals.decision');
    Route::post('/moderation/{sourceType}/{sourceId}/request-resolve', [FounderOperationsController::class, 'requestModerationResolve'])->name('moderation.request-resolve');
    Route::post('/moderation-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideModerationResolve'])->name('moderation-approvals.decision');
    Route::post('/email-drafts/{draft}/request-send', [FounderOperationsController::class, 'requestEmailSend'])->name('email-drafts.request-send');
    Route::post('/email-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideEmailSend'])->name('email-approvals.decision');
    Route::post('/content-drafts/{draft}/request-publish', [FounderOperationsController::class, 'requestContentPublish'])->name('content-drafts.request-publish');
    Route::post('/content-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideContentPublish'])->name('content-approvals.decision');
});
