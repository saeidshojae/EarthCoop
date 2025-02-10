<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;

use App\Http\Controllers\IndustrialFieldController;

use App\Http\Controllers\SpecializationController;

use App\Http\Controllers\LocationController;

use App\Http\Controllers\Auth\LoginController;

// Routeهای کاربران

Route::resource('users', UserController::class);

// Routeهای رسته‌های صنفی

Route::resource('industrial-fields', IndustrialFieldController::class);

// Routeهای تخصص‌ها

Route::resource('specializations', SpecializationController::class);

// Routeهای مکان‌ها

Route::resource('locations', LocationController::class);


Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->middleware('auth');

Route::get('/', function () {
    return view('welcome');

// تعریف مسیر GET برای نمایش فرم ورود
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// تعریف مسیر POST برای پردازش فرم ورود
Route::post('/login', [LoginController::class, 'login']);    
});
