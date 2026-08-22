<?php

use App\Http\Controllers\Elections\ElectionFeedbackTopicResponseController;
use App\Http\Controllers\Elections\ElectionProcessReviewController;
use App\Http\Controllers\Elections\ResponsibilityOfferController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/profile/accept-candidate/{type}', [ResponsibilityOfferController::class, 'legacyConfirmation'])
        ->name('profile.accept.candidate');

    Route::post('/elections/responsibility-offers/{offer}/{decision}', [ResponsibilityOfferController::class, 'respond'])
        ->whereNumber('offer')
        ->whereIn('decision', ['accept', 'decline'])
        ->name('elections.responsibility-offers.respond');

    Route::get('/elections/{election}/feedback-topic-responses', [ElectionFeedbackTopicResponseController::class, 'index'])
        ->whereNumber('election')->name('elections.feedback-topic-responses.index');
    Route::post('/elections/{election}/feedback-topic-responses', [ElectionFeedbackTopicResponseController::class, 'store'])
        ->whereNumber('election')->name('elections.feedback-topic-responses.store');

    Route::post('/elections/{election}/process-reviews', [ElectionProcessReviewController::class, 'store'])
        ->whereNumber('election')->name('elections.process-reviews.store');
    Route::get('/elections/process-reviews/{review}', [ElectionProcessReviewController::class, 'show'])
        ->whereNumber('review')->name('elections.process-reviews.show');
    Route::post('/elections/process-reviews/{review}/human', [ElectionProcessReviewController::class, 'requestHuman'])
        ->whereNumber('review')->name('elections.process-reviews.human');
    Route::post('/elections/process-reviews/{review}/endorse', [ElectionProcessReviewController::class, 'endorse'])
        ->whereNumber('review')->name('elections.process-reviews.endorse');

    Route::middleware(AdminMiddleware::class)->group(function () {
        Route::post('/admin/elections/process-reviews/{review}/stay', [ElectionProcessReviewController::class, 'stay'])
            ->whereNumber('review')->name('admin.elections.process-reviews.stay');
        Route::post('/admin/elections/process-reviews/{review}/decision', [ElectionProcessReviewController::class, 'decide'])
            ->whereNumber('review')->name('admin.elections.process-reviews.decision');
    });
});
