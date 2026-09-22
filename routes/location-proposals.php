<?php

use App\Http\Controllers\Location\LocationProposalController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\LocationStructureClaimController;
use App\Http\Controllers\LocationGovernance\LocationProposalStructureClaimController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Registration Step 3 must be able to traverse an already-open proposal before
// authentication completes. Keep only that read-only traversal public.
Route::get('/location/proposals/{locationProposal}/children', [LocationOptionsController::class, 'proposalChildren'])
    ->name('locations.proposals.children');

// Proposal/claim creation and support mutate community state and require a real user.
// Keeping these routes authenticated also preserves the non-null User contract used
// by the proposal and structural-claim services.
Route::middleware(Authenticate::class)->group(function () {
    Route::post('/locations/structure-claims', [LocationStructureClaimController::class, 'store'])
        ->name('locations.structure-claims.store');

    Route::post('/location/proposals/{locationProposal}/structure-claims', [LocationProposalStructureClaimController::class, 'store'])
        ->name('location.proposals.structure-claims.store');

    Route::post('/locations/proposals', [LocationProposalController::class, 'store'])
        ->name('locations.proposals.store');

    Route::post('/locations/proposals/{locationProposal}/support', [LocationProposalController::class, 'support'])
        ->name('locations.proposals.support');
});
