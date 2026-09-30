<?php

use App\Http\Controllers\Profile\CommunicationPreferenceController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function (): void {
    Route::get('/profile/communication-preferences', [CommunicationPreferenceController::class, 'index'])
        ->name('profile.communication-preferences');
    Route::put('/profile/communication-preferences', [CommunicationPreferenceController::class, 'update'])
        ->name('profile.communication-preferences.update');
});

Route::get('/communications/unsubscribe/{user}', [CommunicationPreferenceController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('communications.unsubscribe');
