<?php

use App\Http\Controllers\Group\CanonicalGroupSearchController;
use App\Http\Controllers\LocationGovernance\CanonicalGroupIndexController;
use Illuminate\Support\Facades\Route;

// Canonical authenticated registration for the My Groups index.
// This loads after the legacy monolithic web.php definition. The adapter is
// fail-safe: while the Stage C flag is disabled it delegates to the mature
// legacy GroupController@index unchanged.
Route::get('/groups', CanonicalGroupIndexController::class)
    ->middleware('auth')
    ->name('groups.index');

// Shadow only the legacy group-search closure after web.php. The surrounding
// route group is authenticated for /groups, but AJAX group search historically
// returns JSON 401 rather than a login redirect, so preserve that contract here.
Route::get('/api/groups/search', CanonicalGroupSearchController::class)
    ->withoutMiddleware(\App\Http\Middleware\Authenticate::class);
