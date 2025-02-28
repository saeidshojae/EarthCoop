<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\JobFieldController;
use App\Http\Controllers\SpecializationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\Auth\RegisterController;

// مسیرهای پروفایل کاربر - نیازمند احراز هویت
Route::middleware('auth')->group(function () {
    Route::get('/profile', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
});

// مدیریت کاربران - نیازمند احراز هویت
Route::resource('users', UserController::class)->middleware('auth');
Route::get('/users', [UserController::class, 'index'])->name('users.index');

// مدیریت رسته‌های صنفی
Route::resource('job-fields', JobFieldController::class)->middleware('auth');
Route::get('/job-fields/{parentId}/subcategories', [JobFieldController::class, 'getSubcategories']);

// مدیریت تخصص‌ها
Route::resource('specializations', SpecializationController::class)->middleware('auth');
Route::get('/specializations/{parentId}/subcategories', [SpecializationController::class, 'getSubcategories']);

// مدیریت مکان‌ها
Route::resource('locations', LocationController::class)->middleware('auth');

// مسیرهای مرتبط با ثبت‌نام چند مرحله‌ای
Route::get('/register/step1', [RegisterController::class, 'showStep1Form'])->name('register.step1');
Route::post('/register/step1', [RegisterController::class, 'postStep1Form'])->name('register.step1.post');

Route::get('/register/step2', [RegisterController::class, 'showStep2Form'])->name('register.step2');
Route::post('/register/step2', [RegisterController::class, 'postStep2Form'])->name('register.step2.post');

Route::get('/register/step3', [RegisterController::class, 'showStep3Form'])->name('register.step3');
Route::post('/register/step3', [RegisterController::class, 'postStep3Form'])->name('register.step3.post');

// مدیریت ورود و خروج کاربران
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// مسیرهای مرتبط با تأیید صحت اطلاعات
Route::post('/verify', [VerifyController::class, 'verify'])->name('verify');

// مسیرهای صفحه اصلی و خانه
Route::get('/', function () {
    return view('welcome');
});
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// مسیرهای مرتبط با مکان‌یابی
Route::get('/countries/{continent_id}', [LocationController::class, 'getCountries']);
Route::get('/provinces/{country_id}', [LocationController::class, 'getProvinces']);
Route::get('/counties/{province_id}', [LocationController::class, 'getCounties']);
Route::get('/districts/{county_id}', [LocationController::class, 'getDistricts']);
Route::get('/settlements/{district_id}', [LocationController::class, 'getSettlements']);
Route::get('/localities/{settlement_id}', [LocationController::class, 'getLocalities']);
Route::get('/neighborhoods/{locality_id}', [LocationController::class, 'getNeighborhoods']);
Route::get('/streets/{neighborhood_id}', [LocationController::class, 'getStreets']);
Route::get('/alleys/{street_id}', [LocationController::class, 'getAlleys']);

// مسیر تستی
Route::get('/test', function () {
    dd('فرم با موفقیت ارسال شد');
});

// فعال‌سازی احراز هویت پیش‌فرض لاراول
Auth::routes();
