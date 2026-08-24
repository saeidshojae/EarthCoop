<?php

use App\Http\Controllers\Admin\ElectionConflictPolicyController;
use App\Http\Controllers\Admin\ElectionManagementController;
use App\Http\Controllers\Admin\ElectionPolicyOverrideController;
use App\Http\Controllers\Admin\ElectionResponsibilityContractAdminController;
use App\Http\Controllers\Elections\ElectionFeedbackTopicResponseController;
use App\Http\Controllers\Elections\ElectionProcessReviewController;
use App\Http\Controllers\Elections\ElectionResponsibilityContractController;
use App\Http\Controllers\Elections\ElectionUserPortalController;
use App\Http\Controllers\Elections\ResponsibilityOfferController;
use App\Http\Controllers\Group\SystemicElectionChatController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// Public, stable reference page for foundational documents, onboarding and group UI.
Route::view('/elections/guideline', 'elections.guideline')
    ->name('elections.guideline');

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/groups/chat/{group}', [SystemicElectionChatController::class, 'chat'])
        ->middleware(['group.chat.csp', 'group.chat.context'])
        ->name('groups.chat');

    Route::get('/groups/{group}/elections', [ElectionUserPortalController::class, 'show'])
        ->name('elections.portal');

    Route::get('/profile/accept-candidate/{type}',[ResponsibilityOfferController::class,'legacyConfirmation'])->name('profile.accept.candidate');
    Route::post('/elections/responsibility-offers/{offer}/{decision}',[ResponsibilityOfferController::class,'respond'])->whereNumber('offer')->whereIn('decision',['accept','decline'])->name('elections.responsibility-offers.respond');
    Route::get('/elections/responsibility-contracts/{contract}/download',[ElectionResponsibilityContractController::class,'download'])->whereNumber('contract')->name('elections.responsibility-contracts.download');
    Route::get('/elections/{election}/feedback-topic-responses',[ElectionFeedbackTopicResponseController::class,'index'])->whereNumber('election')->name('elections.feedback-topic-responses.index');
    Route::post('/elections/{election}/feedback-topic-responses',[ElectionFeedbackTopicResponseController::class,'store'])->whereNumber('election')->name('elections.feedback-topic-responses.store');
    Route::post('/elections/{election}/process-reviews',[ElectionProcessReviewController::class,'store'])->whereNumber('election')->name('elections.process-reviews.store');
    Route::get('/elections/process-reviews/{review}',[ElectionProcessReviewController::class,'show'])->whereNumber('review')->name('elections.process-reviews.show');
    Route::post('/elections/process-reviews/{review}/human',[ElectionProcessReviewController::class,'requestHuman'])->whereNumber('review')->name('elections.process-reviews.human');
    Route::post('/elections/process-reviews/{review}/endorse',[ElectionProcessReviewController::class,'endorse'])->whereNumber('review')->name('elections.process-reviews.endorse');

    Route::middleware(AdminMiddleware::class)->group(function () {
        Route::get('/admin/elections', [ElectionManagementController::class, 'index'])
            ->name('admin.elections.dashboard');
        Route::get('/admin/elections/contracts',[ElectionResponsibilityContractAdminController::class,'index'])->name('admin.elections.contracts.index');
        Route::post('/admin/elections/contracts',[ElectionResponsibilityContractAdminController::class,'store'])->name('admin.elections.contracts.store');
        Route::get('/admin/elections/conflict-policy',[ElectionConflictPolicyController::class,'index'])->name('admin.elections.conflict-policy.index');
        Route::post('/admin/elections/conflict-policy',[ElectionConflictPolicyController::class,'store'])->name('admin.elections.conflict-policy.store');
        Route::get('/admin/elections/{election}/policy-override',[ElectionPolicyOverrideController::class,'edit'])->whereNumber('election')->name('admin.elections.policy-override.edit');
        Route::post('/admin/elections/{election}/policy-override',[ElectionPolicyOverrideController::class,'update'])->whereNumber('election')->name('admin.elections.policy-override.update');

        Route::middleware('permission:elections.review.manage')->group(function () {
            Route::get('/admin/elections/reviews',[ElectionManagementController::class,'reviews'])->name('admin.elections.reviews');
            Route::post('/admin/elections/reviews/{review}/stay',[ElectionManagementController::class,'stay'])->whereNumber('review')->name('admin.elections.reviews.stay');
            Route::post('/admin/elections/reviews/{review}/decision',[ElectionManagementController::class,'decide'])->whereNumber('review')->name('admin.elections.reviews.decision');

            Route::post('/admin/elections/process-reviews/{review}/stay',[ElectionProcessReviewController::class,'stay'])->whereNumber('review')->name('admin.elections.process-reviews.stay');
            Route::post('/admin/elections/process-reviews/{review}/decision',[ElectionProcessReviewController::class,'decide'])->whereNumber('review')->name('admin.elections.process-reviews.decision');
        });
    });
});
