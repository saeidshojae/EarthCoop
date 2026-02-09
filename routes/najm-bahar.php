<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NajmBaharController;

/*
|--------------------------------------------------------------------------
| Najm Bahar Routes
|--------------------------------------------------------------------------
| Routes for the new Najm Bahar financial system
*/

Route::middleware(['auth'])->group(function () {
    // نمایش توافقنامه نجم بهار
    Route::get('/najm-bahar/agreement', [NajmBaharController::class, 'showAgreement'])
        ->name('najm-bahar.agreement');
    
    // پردازش تایید توافقنامه و ایجاد حساب
    Route::post('/najm-bahar/agreement', [NajmBaharController::class, 'processAgreement'])
        ->name('najm-bahar.agreement.process');
    
    // داشبورد نجم بهار (این روت قبلاً وجود دارد اما کنترلر متفاوت است)
    // Route::get('/najm-bahar/dashboard', [NajmBaharController::class, 'dashboard'])
    //     ->name('najm-bahar.dashboard');
});