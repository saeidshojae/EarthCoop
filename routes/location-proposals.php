<?php

use App\Http\Controllers\Location\LocationProposalController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\LocationStructureClaimController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::post('/locations/structure-claims', [LocationStructureClaimController::class, 'store'])
        ->name('locations.structure-claims.store');

    Route::post('/locations/proposals', [LocationProposalController::class, 'store'])
        ->name('locations.proposals.store');

    Route::get('/location/proposals/{locationProposal}/children', [LocationOptionsController::class, 'proposalChildren'])
        ->name('locations.proposals.children');

    Route::post('/locations/proposals/{locationProposal}/support', [LocationProposalController::class, 'support'])
        ->name('locations.proposals.support');
});
