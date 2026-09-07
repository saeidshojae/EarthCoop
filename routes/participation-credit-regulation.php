<?php

use App\Http\Controllers\ParticipationCreditRegulationController;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/participation/credit-regulation', ParticipationCreditRegulationController::class)
    ->middleware(Authenticate::class)
    ->name('participation.credit-regulation');
