<?php

use App\Http\Controllers\Admin\FounderMinistryChatController;
use App\Http\Controllers\Admin\FounderOperationsController;
use App\Http\Controllers\Admin\FounderOperationsDeskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:najm-hoda-autonomy-read'])->prefix('admin/najm-hoda/founder-ops')->name('admin.najm-hoda.founder-ops.')->group(function (): void {
    Route::get('/', FounderOperationsDeskController::class)->name('index');
    Route::get('/ministry/readiness', [FounderMinistryChatController::class, 'readiness'])->name('ministry.readiness');
    Route::post('/ministry/chat', FounderMinistryChatController::class)->name('ministry.chat');
    Route::get('/brief', [FounderOperationsController::class, 'brief'])->name('brief');
    Route::get('/snapshot', [FounderOperationsController::class, 'snapshot'])->name('snapshot');
    Route::get('/connectivity', [FounderOperationsController::class, 'connectivity'])->name('connectivity');
    Route::get('/work-queue', [FounderOperationsController::class, 'workQueue'])->name('work-queue');
    Route::get('/acceptance-status', [FounderOperationsController::class, 'acceptanceStatus'])->name('acceptance-status');
    Route::get('/autonomy-plan', [FounderOperationsController::class, 'autonomyPlan'])->name('autonomy-plan');
    Route::get('/approvals', [FounderOperationsController::class, 'approvals'])->name('approvals');
    Route::get('/authority', [FounderOperationsController::class, 'authority'])->name('authority');
});

Route::middleware(['throttle:najm-hoda-autonomy-write'])->prefix('admin/najm-hoda/founder-ops')->name('admin.najm-hoda.founder-ops.')->group(function (): void {
    Route::patch('/support-drafts/{draft}', [FounderOperationsController::class, 'updateSupportDraft'])->name('support-drafts.update');
    Route::post('/support-drafts/{draft}/request-send', [FounderOperationsController::class, 'requestSupportDraftSend'])->name('support-drafts.request-send');
    Route::post('/support-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideSupportDraft'])->name('support-approvals.decision');
    Route::post('/reference/{type}/{id}/request-approve', [FounderOperationsController::class, 'requestReferenceApprove'])->name('reference.request-approve');
    Route::post('/reference-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideReferenceApproval'])->name('reference-approvals.decision');
    Route::post('/moderation/{sourceType}/{sourceId}/request-resolve', [FounderOperationsController::class, 'requestModerationResolve'])->name('moderation.request-resolve');
    Route::post('/moderation-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideModerationResolve'])->name('moderation-approvals.decision');
    Route::patch('/email-drafts/{draft}', [FounderOperationsController::class, 'updateEmailDraft'])->name('email-drafts.update');
    Route::post('/email-drafts/{draft}/request-send', [FounderOperationsController::class, 'requestEmailSend'])->name('email-drafts.request-send');
    Route::post('/email-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideEmailSend'])->name('email-approvals.decision');
    Route::patch('/content-drafts/{draft}', [FounderOperationsController::class, 'updateContentDraft'])->name('content-drafts.update');
    Route::post('/content-drafts/{draft}/request-publish', [FounderOperationsController::class, 'requestContentPublish'])->name('content-drafts.request-publish');
    Route::post('/content-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideContentPublish'])->name('content-approvals.decision');
    Route::patch('/announcement-drafts/{draft}', [FounderOperationsController::class, 'updateAnnouncementDraft'])->name('announcement-drafts.update');
    Route::post('/announcement-drafts/{draft}/request-publish', [FounderOperationsController::class, 'requestAnnouncementPublish'])->name('announcement-drafts.request-publish');
    Route::post('/announcement-approvals/{requestId}/decision', [FounderOperationsController::class, 'decideAnnouncementPublish'])->name('announcement-approvals.decision');
});
