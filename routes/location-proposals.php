<?php

use App\Http\Controllers\Location\LocationProposalController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::post('/locations/proposals', [LocationProposalController::class, 'store'])
        ->name('locations.proposals.store');

    Route::post('/locations/proposals/{locationProposal}/support', [LocationProposalController::class, 'support'])
        ->name('locations.proposals.support');
});
