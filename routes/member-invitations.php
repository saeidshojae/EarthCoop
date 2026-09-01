<?php

use App\Http\Controllers\Profile\MemberInvitationController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Loaded after routes/web.php. Public non-member invitation-request routes stay
// untouched. The legacy GET issuance URI is deliberately shadowed with a safe
// redirect so a refresh/prefetch can never create a code.
Route::middleware(Authenticate::class)
    ->get('/my-invation-code', [MemberInvitationController::class, 'index'])
    ->name('my-invation-code');

Route::middleware(Authenticate::class)
    ->get('/profile/invation-code-generate', fn () => redirect()->route('my-invation-code'))
    ->name('profile.generate-code');

Route::middleware(Authenticate::class)
    ->post('/profile/invation-code-generate', MemberInvitationController::class)
    ->name('profile.member-invitations.store');
