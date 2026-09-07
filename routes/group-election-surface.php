<?php

use App\Http\Controllers\Group\GroupElectionSurfaceController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/groups/{group}/election-surface/stats', [GroupElectionSurfaceController::class, 'stats'])
        ->name('groups.election-surface.stats');
});
