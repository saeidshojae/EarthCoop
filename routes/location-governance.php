<?php

use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('location/options')->name('location.options.')->group(function () {
    Route::get('/root', [LocationOptionsController::class, 'root'])->name('root');
    Route::get('/{location}/children', [LocationOptionsController::class, 'children'])->name('children');
});
