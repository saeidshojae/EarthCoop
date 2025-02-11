<?php


Route::middleware('auth')->group(function () {
    Route::get('/profile', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
});

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\IndustrialFieldController;
use App\Http\Controllers\SpecializationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserProfileController;

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

// تعریف مسیر GET برای نمایش فرم ورود
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// تعریف مسیر POST برای پردازش فرم ورود
Route::post('/login', [LoginController::class, 'login']);

// تعریف مسیر POST برای خروج
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/users', [UserController::class, 'index'])->name('users.index');