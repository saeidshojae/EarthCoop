<?php

use App\Http\Controllers\API\V1\Auth\NativeSessionController;
use App\Http\Controllers\API\V1\ElectionController;
use App\Http\Controllers\API\V1\GroupController;
use App\Http\Controllers\API\V1\GroupFeedController;
use App\Http\Controllers\API\V1\LocationGovernanceController;
use App\Http\Controllers\API\V1\NajmHodaCapabilityController;
use App\Http\Controllers\API\V1\NajmHodaConversationController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\ProjectController;
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
        Route::get('/auth/session', [NativeSessionController::class, 'show'])->name('auth.session.show');
        Route::post('/auth/session/rotate', [NativeSessionController::class, 'rotate'])->name('auth.session.rotate');
        Route::delete('/auth/session', [NativeSessionController::class, 'destroy'])->name('auth.session.destroy');

        Route::get('/me', ProfileController::class)->name('me');
        Route::get('/location/options/root', [LocationGovernanceController::class, 'root'])->name('location.options.root');
        Route::get('/location/options/{location}/children', [LocationGovernanceController::class, 'children'])->name('location.options.children');
        Route::get('/location/proposals/{locationProposal}/children', [LocationGovernanceController::class, 'proposalChildren'])->name('location.proposals.children');
        Route::get('/location-governance/me', [LocationGovernanceController::class, 'me'])->name('location-governance.me');
        Route::put('/location-governance/residence', [LocationGovernanceController::class, 'updateResidence'])->name('location-governance.residence.update');

        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
        Route::get('/groups/{group}/feed/delta', [GroupFeedController::class, 'delta'])->name('groups.feed.delta');
        Route::get('/groups/{group}/unread', [GroupFeedController::class, 'unread'])->name('groups.unread');
        Route::post('/groups/{group}/read', [GroupFeedController::class, 'read'])->middleware('api.v1.idempotency')->name('groups.read');

        Route::get('/groups/{group}/elections/current', [ElectionController::class, 'current'])->name('elections.current');
        Route::put('/elections/{election}/ballot', [ElectionController::class, 'ballot'])->middleware('api.v1.idempotency')->name('elections.ballot');

        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects', [ProjectController::class, 'store'])->middleware('api.v1.idempotency')->name('projects.store');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('api.v1.idempotency')->name('projects.update');
        Route::post('/projects/{project}/submit', [ProjectController::class, 'submit'])->middleware('api.v1.idempotency')->name('projects.submit');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('api.v1.idempotency')->name('notifications.read');
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->middleware('api.v1.idempotency')->name('notifications.preferences.update');

        Route::get('/najm-hoda/conversations', [NajmHodaConversationController::class, 'index'])->name('najm-hoda.conversations.index');
        Route::post('/najm-hoda/conversations', [NajmHodaConversationController::class, 'store'])->name('najm-hoda.conversations.store');
        Route::get('/najm-hoda/conversations/{conversation}', [NajmHodaConversationController::class, 'show'])->name('najm-hoda.conversations.show');
        Route::post('/najm-hoda/conversations/{conversation}/messages', [NajmHodaConversationController::class, 'message'])->name('najm-hoda.conversations.messages.store');
        Route::get('/najm-hoda/capabilities', [NajmHodaCapabilityController::class, 'index'])->name('najm-hoda.capabilities.index');
        Route::get('/najm-hoda/capabilities/{action}', [NajmHodaCapabilityController::class, 'show'])->name('najm-hoda.capabilities.show');
    });
});
