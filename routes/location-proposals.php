<?php

use App\Http\Controllers\Location\LocationProposalController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\LocationStructureClaimController;
use App\Http\Controllers\LocationGovernance\LocationProposalStructureClaimController;
use Illuminate\Support\Facades\Route;

// Registration Step 3 is intentionally usable before an authenticated session exists.
// Write actions below validate their actor in the controller/service; read-only proposal
// traversal must remain available to the registration picker.
Route::group(function () {
    Route::post('/locations/structure-claims', [LocationStructureClaimController::class, 'store'])
        ->name('locations.structure-claims.store');
    Route::post('/location/proposals/{locationProposal}/structure-claims', [LocationProposalStructureClaimController::class, 'store'])
        ->name('location.proposals.structure-claims.store');

    Route::post('/locations/proposals', [LocationProposalController::class, 'store'])
        ->name('locations.proposals.store');

    Route::get('/location/proposals/{locationProposal}/children', [LocationOptionsController::class, 'proposalChildren'])
        ->name('locations.proposals.children');

    Route::post('/locations/proposals/{locationProposal}/support', [LocationProposalController::class, 'support'])
        ->name('locations.proposals.support');
});
