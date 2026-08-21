<?php

use App\Http\Controllers\Elections\ResponsibilityOfferController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    // Loaded after web.php so the legacy route name/URI is safely overridden:
    // GET is now read-only and leads to a CSRF-protected POST confirmation.
    Route::get('/profile/accept-candidate/{type}', [ResponsibilityOfferController::class, 'legacyConfirmation'])
        ->name('profile.accept.candidate');

    Route::post('/elections/responsibility-offers/{offer}/{decision}', [ResponsibilityOfferController::class, 'respond'])
        ->whereNumber('offer')
        ->whereIn('decision', ['accept', 'decline'])
        ->name('elections.responsibility-offers.respond');
});
