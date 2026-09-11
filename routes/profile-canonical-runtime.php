<?php

use App\Http\Controllers\Profile\RuntimeProfileController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Loaded after web.php so only the profile read route is shadowed. The runtime
// controller delegates back to the legacy ProfileController when the canonical
// runtime flag is disabled, preserving rollback without requiring Address on
// the canonical path.
Route::middleware(Authenticate::class)
    ->get('/profile', [RuntimeProfileController::class, 'showProfile'])
    ->name('profile.show');
