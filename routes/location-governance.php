<?php

use App\Http\Controllers\LocationGovernance\GeolocationController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\ProfileEditController;
use App\Http\Controllers\LocationGovernance\ProfileResidenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('location/options')->name('location.options.')->group(function () {
    Route::get('/root', [LocationOptionsController::class, 'root'])->name('root');
    Route::get('/{location}/children', [LocationOptionsController::class, 'children'])->name('children');
});

Route::middleware('auth')->post('/location-governance/geolocation/match', [GeolocationController::class, 'match'])
    ->name('location.geolocation.match');

// Loaded after routes/web.php. These deliberately shadow the legacy profile
// location endpoints. Each canonical adapter delegates straight back to the
// legacy ProfileController while the rollout flag is disabled.
Route::middleware('auth')->get('/profile/edit', ProfileEditController::class)
    ->name('profile.edit');

Route::middleware('auth')->put('/profile/update/address', [ProfileResidenceController::class, 'update'])
    ->name('profile.update.address');
