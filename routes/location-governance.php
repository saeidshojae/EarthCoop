<?php

use App\Http\Controllers\Admin\UserResidenceController;
use App\Http\Controllers\LocationGovernance\CommunityAreaController;
use App\Http\Controllers\LocationGovernance\GeolocationController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\MyLocationGovernanceController;
use App\Http\Controllers\LocationGovernance\ProfileEditController;
use App\Http\Controllers\LocationGovernance\ProfileResidenceController;
use App\Http\Controllers\LocationGovernance\ResidenceOptionsController;
use App\Http\Controllers\LocationGovernance\ProjectScopeOptionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('location/options')->name('location.options.')->group(function () {
    Route::get('/root', [LocationOptionsController::class, 'root'])->name('root');
    Route::get('/{location}/children', [LocationOptionsController::class, 'children'])->name('children');
});

Route::prefix('location/residence/options')->name('location.residence.options.')->group(function () {
    Route::get('/root', [ResidenceOptionsController::class, 'root'])->name('root');
    Route::get('/governance/{governanceArea}/children', [ResidenceOptionsController::class, 'children'])->name('governance.children');
});

Route::prefix('location/project-scope/options')->name('location.project-scope.options.')->group(function () {
    Route::get('/root', [ProjectScopeOptionsController::class, 'root'])->name('root');
    Route::get('/path', [ProjectScopeOptionsController::class, 'path'])->name('path');
    Route::get('/governance/{governanceArea}/children', [ProjectScopeOptionsController::class, 'children'])
        ->name('governance.children');
});

Route::middleware('auth')->post('/location-governance/geolocation/match', [GeolocationController::class, 'match'])
    ->name('location.geolocation.match');

Route::middleware('auth')->get('/location-governance/me', MyLocationGovernanceController::class)
    ->name('location-governance.me');

Route::middleware('auth')->post('/location-governance/community/{location}', [CommunityAreaController::class, 'store'])
    ->name('location-governance.community.store');

// Loaded after routes/web.php. These deliberately shadow the legacy profile
// location endpoints. Each canonical adapter delegates straight back to the
// legacy controller/view while the rollout flag is disabled.
Route::middleware('auth')->get('/profile/edit', ProfileEditController::class)
    ->name('profile.edit');

Route::middleware('auth')->put('/profile/update/address', [ProfileResidenceController::class, 'update'])
    ->name('profile.update.address');

Route::middleware(['auth', 'admin', 'permission:users.edit'])
    ->put('/admin/user/{user}/residence', [UserResidenceController::class, 'update'])
    ->name('admin.users.residence.update');
