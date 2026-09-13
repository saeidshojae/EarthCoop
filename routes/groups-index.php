<?php

use App\Http\Controllers\LocationGovernance\CanonicalGroupIndexController;
use Illuminate\Support\Facades\Route;

// Canonical authenticated registration for the My Groups index.
// This loads after the legacy monolithic web.php definition. The adapter is
// fail-safe: while the Stage C flag is disabled it delegates to the mature
// legacy GroupController@index unchanged.
Route::get('/groups', CanonicalGroupIndexController::class)
    ->middleware('auth')
    ->name('groups.index');
