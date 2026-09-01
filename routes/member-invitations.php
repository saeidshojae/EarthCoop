<?php

use App\Http\Controllers\Profile\MemberInvitationController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Loaded after routes/web.php so these canonical member invitation endpoints
// shadow only the legacy member page/action. Public non-member invitation
// request routes remain owned by routes/web.php.
Route::middleware(Authenticate::class)
    ->get('/my-invation-code', [MemberInvitationController::class, 'index'])
    ->name('my-invation-code');

Route::middleware(Authenticate::class)
    ->get('/profile/invation-code-generate', MemberInvitationController::class)
    ->name('profile.generate-code');
