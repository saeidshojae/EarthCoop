<?php

use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\ProfileResidenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('location/options')->name('location.options.')->group(function () {
    Route::get('/root', [LocationOptionsController::class, 'root'])->name('root');
    Route::get('/{location}/children', [LocationOptionsController::class, 'children'])->name('children');
});

// Loaded after routes/web.php. This deliberately shadows the legacy profile
// address mutation endpoint while preserving exact rollback behavior inside
// ProfileResidenceController when the canonical registration flag is disabled.
Route::middleware('auth')->put('/profile/update/address', [ProfileResidenceController::class, 'update'])
    ->name('profile.update.address');
