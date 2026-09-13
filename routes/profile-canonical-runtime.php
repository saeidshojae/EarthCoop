<?php

use App\Http\Controllers\LocationGovernance\CanonicalProfileMembershipController;
use App\Http\Controllers\Profile\RuntimeProfileController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Loaded after web.php so canonical runtime adapters can shadow only the mature
// endpoints whose spatial/membership side effects must be replaced. Every adapter
// delegates back to the legacy controller while its rollout flag is disabled.
Route::middleware(Authenticate::class)->group(function (): void {
    Route::get('/profile', [RuntimeProfileController::class, 'showProfile'])
        ->name('profile.show');

    Route::put('/profile/update/experience', [CanonicalProfileMembershipController::class, 'updateExperience'])
        ->name('profile.update.experience');

    Route::put('/profile/update/general', [CanonicalProfileMembershipController::class, 'updateGeneral'])
        ->name('profile.update.general');
});
