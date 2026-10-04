<?php

use App\Http\Controllers\API\V1\ActorController;
use App\Http\Controllers\API\V1\Auth\NativeSessionController;
use App\Http\Controllers\API\V1\BootstrapController;
use App\Http\Controllers\API\V1\DevicePushController;
use App\Http\Controllers\API\V1\ElectionController;
use App\Http\Controllers\API\V1\GroupController;
use App\Http\Controllers\API\V1\GroupFeedController;
use App\Http\Controllers\API\V1\GroupMessageController;
use App\Http\Controllers\API\V1\LocationGovernanceController;
use App\Http\Controllers\API\V1\MediaController;
use App\Http\Controllers\API\V1\NajmBaharAccountController;
use App\Http\Controllers\API\V1\NajmBaharActivationController;
use App\Http\Controllers\API\V1\NajmBaharMembershipFeeController;
use App\Http\Controllers\API\V1\NajmBaharScheduledOperationController;
use App\Http\Controllers\API\V1\NajmBaharTransactionController;
use App\Http\Controllers\API\V1\NajmHodaActionController;
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

    Route::get('/bootstrap', BootstrapController::class)->name('bootstrap');

    Route::post('/auth/session', [NativeSessionController::class, 'store'])
        ->name('auth.session.store');

    Route::middleware(['auth:sanctum', 'api.v1.device'])->group(function () {
        Route::get('/auth/session', [NativeSessionController::class, 'show'])->name('auth.session.show');
        Route::post('/auth/session/rotate', [NativeSessionController::class, 'rotate'])->name('auth.session.rotate');
        Route::delete('/auth/session', [NativeSessionController::class, 'destroy'])->name('auth.session.destroy');

        Route::put('/devices/{device}/push', [DevicePushController::class, 'update'])->name('devices.push.update');
        Route::delete('/devices/{device}/push', [DevicePushController::class, 'destroy'])->name('devices.push.destroy');
        Route::post('/media', [MediaController::class, 'store'])->middleware('api.v1.idempotency')->name('media.store');

        Route::get('/me', ProfileController::class)->name('me');
        Route::get('/actors', [ActorController::class, 'index'])->name('actors.index');
        Route::get('/location/options/root', [LocationGovernanceController::class, 'root'])->name('location.options.root');
        Route::get('/location/options/{location}/children', [LocationGovernanceController::class, 'children'])->name('location.options.children');
        Route::get('/location/proposals/{locationProposal}/children', [LocationGovernanceController::class, 'proposalChildren'])->name('location.proposals.children');
        Route::get('/location-governance/me', [LocationGovernanceController::class, 'me'])->name('location-governance.me');
        Route::put('/location-governance/residence', [LocationGovernanceController::class, 'updateResidence'])->name('location-governance.residence.update');

        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
        Route::get('/groups/{group}/feed/delta', [GroupFeedController::class, 'delta'])->name('groups.feed.delta');
        Route::get('/groups/{group}/unread', [GroupFeedController::class, 'unread'])->name('groups.unread');
        Route::post('/groups/{group}/messages', [GroupMessageController::class, 'store'])
            ->middleware([
                \App\Http\Middleware\PrepareNativeGroupMessage::class,
                \App\Http\Middleware\EnsureMembershipParticipation::class,
                \App\Http\Middleware\EnsureGroupSessionWritable::class,
                'throttle:group-message',
                'api.v1.idempotency',
                'group.chat.timing',
            ])->name('groups.messages.store');
        Route::post('/groups/{group}/read', [GroupFeedController::class, 'read'])->middleware('api.v1.idempotency')->name('groups.read');

        Route::get('/groups/{group}/elections/current', [ElectionController::class, 'current'])->name('elections.current');
        Route::put('/elections/{election}/ballot', [ElectionController::class, 'ballot'])->middleware('api.v1.idempotency')->name('elections.ballot');

        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects', [ProjectController::class, 'store'])
            ->middleware(['api.v1.project-owner-authority', 'api.v1.idempotency'])
            ->name('projects.store');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])
            ->middleware(['can:update,project', 'api.v1.idempotency'])
            ->name('projects.update');
        Route::post('/projects/{project}/submit', [ProjectController::class, 'submit'])
            ->middleware(['can:update,project', 'api.v1.idempotency'])
            ->name('projects.submit');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('api.v1.idempotency')->name('notifications.read');
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->middleware('api.v1.idempotency')->name('notifications.preferences.update');

        Route::get('/najm-bahar/account', [NajmBaharAccountController::class, 'show'])->name('najm-bahar.account.show');
        Route::get('/najm-bahar/accounts/{account}/balance', [NajmBaharAccountController::class, 'balance'])
            ->whereNumber('account')
            ->name('najm-bahar.accounts.balance');
        Route::get('/najm-bahar/transactions', [NajmBaharTransactionController::class, 'index'])
            ->name('najm-bahar.transactions.index');
        Route::post('/najm-bahar/transfers', [NajmBaharTransactionController::class, 'storeTransfer'])
            ->middleware('api.v1.idempotency')
            ->name('najm-bahar.transfers.store');
        Route::get('/najm-bahar/activation/eligibility', [NajmBaharActivationController::class, 'eligibility'])
            ->name('najm-bahar.activation.eligibility');
        Route::post('/najm-bahar/activation', [NajmBaharActivationController::class, 'store'])
            ->middleware('api.v1.idempotency')
            ->name('najm-bahar.activation.store');
        Route::get('/najm-bahar/membership-fee', [NajmBaharMembershipFeeController::class, 'show'])
            ->name('najm-bahar.membership-fee.show');
        Route::post('/najm-bahar/membership-fee/pay', [NajmBaharMembershipFeeController::class, 'pay'])
            ->middleware('api.v1.idempotency')
            ->name('najm-bahar.membership-fee.pay');
        Route::get('/najm-bahar/scheduled-operations', [NajmBaharScheduledOperationController::class, 'index'])
            ->name('najm-bahar.scheduled-operations.index');

        Route::get('/najm-hoda/conversations', [NajmHodaConversationController::class, 'index'])->name('najm-hoda.conversations.index');
        Route::post('/najm-hoda/conversations', [NajmHodaConversationController::class, 'store'])->name('najm-hoda.conversations.store');
        Route::get('/najm-hoda/conversations/{conversation}', [NajmHodaConversationController::class, 'show'])->name('najm-hoda.conversations.show');
        Route::post('/najm-hoda/conversations/{conversation}/messages', [NajmHodaConversationController::class, 'message'])->name('najm-hoda.conversations.messages.store');
        Route::get('/najm-hoda/capabilities', [NajmHodaCapabilityController::class, 'index'])->name('najm-hoda.capabilities.index');
        Route::get('/najm-hoda/capabilities/{action}', [NajmHodaCapabilityController::class, 'show'])->name('najm-hoda.capabilities.show');

        Route::post('/najm-hoda/actions/proposals', [NajmHodaActionController::class, 'propose'])->name('najm-hoda.actions.propose');
        Route::post('/najm-hoda/actions/{action}/consent', [NajmHodaActionController::class, 'consent'])->middleware('api.v1.idempotency')->name('najm-hoda.actions.consent');
        Route::post('/najm-hoda/actions/{action}/apply', [NajmHodaActionController::class, 'apply'])->middleware('api.v1.idempotency')->name('najm-hoda.actions.apply');
        Route::get('/najm-hoda/actions/{action}/evidence', [NajmHodaActionController::class, 'evidence'])->name('najm-hoda.actions.evidence');
    });
});
