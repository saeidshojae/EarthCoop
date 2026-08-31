<?php

use App\Http\Controllers\Group\GroupController;
use Illuminate\Support\Facades\Route;

// Canonical authenticated registration for the My Groups index.
// This loads after the legacy monolithic web.php definition so unauthenticated
// requests are stopped by middleware before GroupController@index is invoked.
Route::get('/groups', [GroupController::class, 'index'])
    ->name('groups.index');
