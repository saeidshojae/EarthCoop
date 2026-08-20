<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Modules\Stock\Controllers\CanonicalAuctionController;
use App\Modules\Stock\Controllers\CanonicalBidController;
use Illuminate\Support\Facades\Route;

// Loaded after routes/web.php so the canonical-aware show route becomes the
// single display entry point while still delegating legacy auctions to the old view.
Route::get('/auctions/{auction}', [CanonicalAuctionController::class, 'show'])
    ->name('auction.show');

Route::middleware(Authenticate::class)->group(function (): void {
    Route::post('/auctions/{auction}/canonical-bid', [CanonicalBidController::class, 'store'])
        ->name('auction.canonical-bid');

    Route::delete('/bids/{bid}/canonical', [CanonicalBidController::class, 'destroy'])
        ->name('bid.canonical.destroy');
});

Route::middleware([Authenticate::class, AdminMiddleware::class])->group(function (): void {
    Route::post('/admin/stock/auctions/{auction}/canonical-close', [CanonicalAuctionController::class, 'close'])
        ->name('admin.stock.canonical-auction.close');
});
