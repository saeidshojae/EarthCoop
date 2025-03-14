<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Step2Controller;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\InvitationCodeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\FileController;

// مسیر ارسال دعوت
Route::post('/profile/send-invitation', [ProfileController::class, 'sendInvitation'])->name('profile.send.invitation');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('invitation-codes', [InvitationCodeController::class, 'index'])->name('invitation_codes.index');
    Route::post('invitation-codes', [InvitationCodeController::class, 'store'])->name('invitation_codes.store');
});

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

// مسیرهای مربوط به صفحات پروفایل (برای کاربران وارد شده)
Route::middleware('auth')->group(function () {
    // صفحه نمایش پروفایل (نمایش اطلاعات ثابت)
    Route::get('/profile', [ProfileController::class, 'showProfile'])->name('profile.show');
    
    // صفحه ویرایش اطلاعات تغییرپذیر (صنف، تخصص، مکان و عکس)
    Route::get('/profile/edit', [ProfileController::class, 'editModifiable'])->name('profile.edit');
    Route::post('/profile/edit', [ProfileController::class, 'updateModifiable'])->name('profile.update.modifiable');
});

// مسیر احراز هویت پیش‌فرض لاراول
// نمایش فرم لاگین
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
// پردازش لاگین (معمولاً لاراول این مسیر رو با POST ارسال می‌کند)
Route::post('/login', [LoginController::class, 'login'])->name('login.process');
// مسیر فراموشی پسورد
Route::get('/password/reset', [LoginController::class, 'showLinkRequestForm'])->name('password.request');
// مسیر خروج از سیستم
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// سیستم ثبت‌نام چندمرحله‌ای
// صفحه اصلی خوش‌آمدگویی ثبت‌نام
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

// صفحه اصلی پس از ثبت‌نام
Route::get('/home', [HomeController::class, 'index'])->name('home');

// مربوط به گروه و چت
Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
Route::post('/groups/{group}/messages', [MessageController::class, 'store'])->name('groups.messages.store');
Route::post('/groups/{group}/files', [FileController::class, 'store'])->name('groups.files.store');