<?php

use App\Http\Controllers\Location\LocationProposalController;
use App\Http\Controllers\LocationGovernance\LocationOptionsController;
use App\Http\Controllers\LocationGovernance\LocationStructureClaimController;
use App\Http\Controllers\LocationGovernance\LocationProposalStructureClaimController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Neutral source catalog only: no residence confirmation, claim, group or election side effects.
Route::get('/location/reference-settlements', [\App\Http\Controllers\LocationGovernance\IranSettlementCatalogController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('location.reference-settlements.index');
Route::get('/location/reference-settlements/{externalId}/children', [\App\Http\Controllers\LocationGovernance\IranSettlementCatalogController::class, 'children'])
    ->where('externalId', 'IR-1404-[1-9][0-9]*')
    ->middleware('throttle:30,1')
    ->name('location.reference-settlements.children');

// Registration Step 3 must be able to traverse an already-open proposal before
// authentication completes. Keep only that read-only traversal public.
Route::get('/location/proposals/{locationProposal}/children', [LocationOptionsController::class, 'proposalChildren'])
    ->name('locations.proposals.children');

// Proposal/claim creation and support mutate community state and require a real user.
// Keeping these routes authenticated also preserves the non-null User contract used
// by the proposal and structural-claim services.
Route::middleware(Authenticate::class)->group(function () {
    Route::post('/location/reference-settlement-residence-claims', [\App\Http\Controllers\LocationGovernance\IranSettlementResidenceClaimController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('location.reference-settlement-residence-claims.store');

    Route::get('/location/reference-settlement-residence-claims', [\App\Http\Controllers\LocationGovernance\IranSettlementResidenceClaimController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('location.reference-settlement-residence-claims.index');

    Route::post('/locations/structure-claims', [LocationStructureClaimController::class, 'store'])
        ->name('locations.structure-claims.store');

    Route::post('/location/proposals/{locationProposal}/structure-claims', [LocationProposalStructureClaimController::class, 'store'])
        ->name('location.proposals.structure-claims.store');

    Route::post('/locations/proposals', [LocationProposalController::class, 'store'])
        ->name('locations.proposals.store');

    Route::post('/locations/proposals/{locationProposal}/support', [LocationProposalController::class, 'support'])
        ->name('locations.proposals.support');
});
