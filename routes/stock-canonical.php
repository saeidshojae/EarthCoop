<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Modules\Stock\Controllers\CanonicalAuctionController;
use App\Modules\Stock\Controllers\CanonicalBidController;
use App\Modules\Stock\Controllers\SecondaryListingController;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function (): void {
    Route::get('/auctions/{auction}', [CanonicalAuctionController::class, 'show'])
        ->name('auction.show');

    Route::post('/auctions/{auction}/canonical-bid', [CanonicalBidController::class, 'store'])
        ->name('auction.canonical-bid');

    Route::delete('/bids/{bid}/canonical', [CanonicalBidController::class, 'destroy'])
        ->name('bid.canonical.destroy');

    Route::get('/holdings/{holding}/secondary-listing', [SecondaryListingController::class, 'create'])
        ->name('stock.secondary-listing.create');
    Route::post('/holdings/{holding}/secondary-listing', [SecondaryListingController::class, 'store'])
        ->name('stock.secondary-listing.store');
    Route::delete('/secondary-listings/{auction}', [SecondaryListingController::class, 'cancel'])
        ->name('stock.secondary-listing.cancel');
});

Route::middleware([Authenticate::class, AdminMiddleware::class])->group(function (): void {
    Route::post('/admin/stock/auctions/{auction}/canonical-close', [CanonicalAuctionController::class, 'close'])
        ->name('admin.stock.canonical-auction.close');
});
