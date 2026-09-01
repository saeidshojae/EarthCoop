<?php

use App\Http\Middleware\Authenticate;
use App\Modules\Stock\Controllers\CanonicalStockBookController;
use Illuminate\Support\Facades\Route;

Route::middleware([Authenticate::class])->group(function (): void {
    Route::get('stock-book', [CanonicalStockBookController::class, 'show'])->name('stock.book');
});
