<?php

use App\Http\Controllers\Profile\MemberInvitationController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Loaded after routes/web.php so this canonical endpoint shadows the legacy
// ProfileController action without disturbing the rest of the profile surface.
Route::middleware(Authenticate::class)
    ->get('/profile/invation-code-generate', MemberInvitationController::class)
    ->name('profile.generate-code');
