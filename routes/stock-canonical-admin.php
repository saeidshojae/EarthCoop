<?php

use App\Http\Middleware\AdminMiddleware;
use App\Modules\Stock\Controllers\CanonicalAdminAuctionController;
use Illuminate\Support\Facades\Route;

Route::middleware([AdminMiddleware::class])->prefix('admin/auctions')->name('admin.auction.')->group(function (): void {
    Route::middleware('permission:stock.create')->group(function (): void {
        Route::get('/create', [CanonicalAdminAuctionController::class, 'create'])->name('create');
        Route::post('/', [CanonicalAdminAuctionController::class, 'store'])->name('store');
    });

    Route::middleware('permission:stock.edit')->group(function (): void {
        Route::get('/{auction}/edit', [CanonicalAdminAuctionController::class, 'edit'])->name('edit');
        Route::put('/{auction}', [CanonicalAdminAuctionController::class, 'update'])->name('update');
    });
});
