<?php

use App\Http\Controllers\Profile\CommunityStoryController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->prefix('community-stories')->name('community-stories.')->group(function () {
    Route::get('/', [CommunityStoryController::class, 'index'])->name('index');
    Route::post('/', [CommunityStoryController::class, 'store'])->name('store');
    Route::post('/{communityStory}/withdraw', [CommunityStoryController::class, 'withdraw'])->name('withdraw');
});
