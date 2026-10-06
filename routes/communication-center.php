<?php

use App\Http\Controllers\Admin\Communication\AutomationController;
use App\Http\Controllers\Admin\Communication\CampaignController;
use App\Http\Controllers\Admin\Communication\DashboardController;
use App\Http\Controllers\Admin\Communication\DeliveryHistoryController;
use App\Http\Controllers\Admin\Communication\FailureController;
use App\Http\Controllers\Admin\Communication\SenderIdentityController;
use App\Http\Controllers\Admin\Communication\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:communications.view')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/history', [DeliveryHistoryController::class, 'index'])->name('history');
    Route::get('/failures', [FailureController::class, 'index'])->name('failures');

    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('templates.show');

    Route::get('/senders', [SenderIdentityController::class, 'index'])->name('senders.index');
    Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}/preview', [CampaignController::class, 'preview'])->name('campaigns.preview');
});

Route::middleware('permission:communications.templates.manage')->group(function (): void {
    Route::post('/templates/{template}/publish', [TemplateController::class, 'publish'])->name('templates.publish');
    Route::post('/templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');
});

Route::middleware('permission:communications.senders.manage')->group(function (): void {
    Route::get('/senders/create', [SenderIdentityController::class, 'create'])->name('senders.create');
    Route::post('/senders', [SenderIdentityController::class, 'store'])->name('senders.store');
    Route::get('/senders/{sender}/edit', [SenderIdentityController::class, 'edit'])->name('senders.edit');
    Route::put('/senders/{sender}', [SenderIdentityController::class, 'update'])->name('senders.update');
    Route::delete('/senders/{sender}', [SenderIdentityController::class, 'destroy'])->name('senders.destroy');
});

Route::middleware('permission:communications.rules.manage')->group(function (): void {
    Route::get('/automations/create', [AutomationController::class, 'create'])->name('automations.create');
    Route::post('/automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::post('/automations/{rule}/deactivate', [AutomationController::class, 'deactivate'])->name('automations.deactivate');
});

Route::middleware('permission:communications.campaigns.create')->group(function (): void {
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
});

Route::middleware('permission:communications.campaigns.approve')->group(function (): void {
    Route::post('/campaigns/{campaign}/confirm', [CampaignController::class, 'confirm'])->name('campaigns.confirm');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
});
