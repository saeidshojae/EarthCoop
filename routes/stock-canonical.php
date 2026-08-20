<?php

use App\Http\Middleware\Authenticate;
use App\Modules\Stock\Controllers\CanonicalBidController;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function (): void {
    Route::post('/auctions/{auction}/canonical-bid', [CanonicalBidController::class, 'store'])
        ->name('auction.canonical-bid');

    Route::delete('/bids/{bid}/canonical', [CanonicalBidController::class, 'destroy'])
        ->name('bid.canonical.destroy');
});
