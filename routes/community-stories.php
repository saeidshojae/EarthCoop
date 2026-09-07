<?php

use App\Http\Controllers\Admin\CommunityStoryController as AdminCommunityStoryController;
use App\Http\Controllers\Profile\CommunityStoryController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->prefix('community-stories')->name('community-stories.')->group(function () {
    Route::get('/', [CommunityStoryController::class, 'index'])->name('index');
    Route::post('/', [CommunityStoryController::class, 'store'])->name('store');
    Route::post('/{communityStory}/withdraw', [CommunityStoryController::class, 'withdraw'])->name('withdraw');
});

Route::middleware([Authenticate::class, AdminMiddleware::class])
    ->prefix('admin/community-stories')
    ->name('admin.community-stories.')
    ->group(function () {
        Route::get('/', [AdminCommunityStoryController::class, 'index'])->name('index');
        Route::post('/{communityStory}/approve', [AdminCommunityStoryController::class, 'approve'])->name('approve');
        Route::post('/{communityStory}/reject', [AdminCommunityStoryController::class, 'reject'])->name('reject');
        Route::post('/{communityStory}/feature', [AdminCommunityStoryController::class, 'feature'])->name('feature');
        Route::post('/{communityStory}/unfeature', [AdminCommunityStoryController::class, 'unfeature'])->name('unfeature');
    });
