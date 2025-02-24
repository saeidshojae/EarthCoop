<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\IndustrialFieldController;
use App\Http\Controllers\SpecializationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\Auth\RegisterController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
});

// Routeهای کاربران
Route::resource('users', UserController::class)->middleware('auth');

// Routeهای رسته‌های صنفی
Route::resource('industrial-fields', IndustrialFieldController::class)->middleware('auth');

// Routeهای تخصص‌ها
Route::resource('specializations', SpecializationController::class)->middleware('auth');

// Routeهای مکان‌ها
Route::resource('locations', LocationController::class)->middleware('auth');

// صفحه اصلی
Route::get('/', function () {
    return view('welcome');
});

Route::post('/verify', [VerifyController::class, 'verify'])->name('verify');

Route::get('/register/step1', [RegisterController::class, 'showStep1Form'])->name('register.step1');
Route::post('/register/step1', [RegisterController::class, 'postStep1Form'])->name('register.step1.post');

Route::get('/register/step2', [RegisterController::class, 'showStep2Form'])->name('register.step2');
Route::post('/register/step2', [RegisterController::class, 'postStep2Form'])->name('register.step2.post');

Route::get('/register/step3', [RegisterController::class, 'showStep3Form'])->name('register.step3');
Route::post('/register/step3', [RegisterController::class, 'postStep3Form'])->name('register.step3.post');

// تعریف مسیر GET برای نمایش فرم ورود
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// تعریف مسیر POST برای پردازش فرم ورود
Route::post('/login', [LoginController::class, 'login']);

// تعریف مسیر POST برای خروج
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/users', [UserController::class, 'index'])->name('users.index');

// مسیرهای جدید
// تغییر نام پارامترها برای خوانایی بهتر
Route::get('/countries/{continent_id}', [LocationController::class, 'getCountries']);
Route::get('/provinces/{country_id}', [LocationController::class, 'getProvinces']);
Route::get('/counties/{province_id}', [LocationController::class, 'getCounties']);
Route::get('/districts/{county_id}', [LocationController::class, 'getDistricts']);
Route::get('/settlements/{district_id}', [LocationController::class, 'getSettlements']);
Route::get('/localities/{settlement_id}', [LocationController::class, 'getLocalities']);
Route::get('/neighborhoods/{locality_id}', [LocationController::class, 'getNeighborhoods']);
Route::get('/streets/{neighborhood_id}', [LocationController::class, 'getStreets']);
Route::get('/alleys/{street_id}', [LocationController::class, 'getAlleys']);
