<?php

use App\Http\Controllers\API\V1\Auth\NativeSessionController;
use App\Http\Controllers\API\V1\GroupController;
use App\Http\Controllers\API\V1\GroupFeedController;
use App\Http\Controllers\API\V1\LocationGovernanceController;
use App\Http\Controllers\API\V1\ProfileController;
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
        Route::get('/auth/session', [NativeSessionController::class, 'show'])
            ->name('auth.session.show');
        Route::post('/auth/session/rotate', [NativeSessionController::class, 'rotate'])
            ->name('auth.session.rotate');
        Route::delete('/auth/session', [NativeSessionController::class, 'destroy'])
            ->name('auth.session.destroy');

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
        Route::post('/groups/{group}/read', [GroupFeedController::class, 'read'])
            ->middleware('api.v1.idempotency')
            ->name('groups.read');
    });
});
