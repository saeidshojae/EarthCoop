<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Step2Controller;
use App\Http\Controllers\Auth\LoginController;

// صفحه اصلی (خوش‌آمدگویی)
Route::get('/', [RegistrationController::class, 'showWelcome'])->name('welcome');

// پردازش موافقت‌نامه ثبت‌نام
Route::post('/register/accept', [RegistrationController::class, 'processAgreement'])->name('register.accept');

// نمایش فرم ثبت‌نام
Route::get('/register', [RegistrationController::class, 'showRegisterForm'])->name('register.form');

// پردازش فرم ثبت‌نام
Route::post('/register', [RegistrationController::class, 'processRegister'])->name('register.process');

// نمایش فرم مرحله ۱
Route::get('/register/step1', [RegistrationController::class, 'showStep1'])->name('register.step1');

// پردازش فرم مرحله ۱
Route::post('/register/step1', [RegistrationController::class, 'processStep1'])->name('register.step1.process');

// نمایش فرم مرحله ۲
Route::get('/register/step2', [Step2Controller::class, 'show'])->name('register.step2');

// پردازش فرم مرحله ۲
Route::post('/register/step2', [Step2Controller::class, 'store'])->name('register.step2.process');

// دریافت فیلدهای فرزند به صورت Ajax
Route::post('/get-children', [Step2Controller::class, 'getChildren'])->name('get.children');

// نمایش فرم مرحله ۳
Route::get('/register/step3', [RegistrationController::class, 'showStep3'])->name('register.step3');

// پردازش فرم مرحله ۳
Route::post('/register/step3', [RegistrationController::class, 'processStep3'])->name('register.step3.process');


Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


// صفحه اصلی پس از ثبت‌نام
Route::get('/home', [HomeController::class, 'index'])->name('home');

