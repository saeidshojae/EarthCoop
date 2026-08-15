<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NajmBaharController;
use App\Http\Controllers\NajmBahar\ProjectController;
use App\Http\Controllers\NajmBahar\InvestmentController;
use App\Http\Controllers\Admin\NajmBahar\ProjectController as AdminProjectController;

/*
|--------------------------------------------------------------------------
| Najm Bahar Routes
|--------------------------------------------------------------------------
| Routes for the new Najm Bahar financial system.
|
| This file is loaded explicitly by RouteServiceProvider inside the `web`
| middleware group. A legacy include also exists in routes/api.php; when this
| file is required from that API group, do not register the web UI routes a
| second time under the /api prefix / API middleware stack.
*/

$loadedInsideApiGroup = collect(Route::getGroupStack())->contains(function (array $group): bool {
    return trim((string) ($group['prefix'] ?? ''), '/') === 'api';
});

if ($loadedInsideApiGroup) {
    return;
}

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

    // ==========================================
    // پروژه‌ها (Projects)
    // ==========================================
    Route::prefix('najm-bahar/projects')->name('najm-bahar.projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/submit', [ProjectController::class, 'submit'])->name('submit');
        
        // AJAX endpoint for category hierarchy
        Route::get('/categories/sub-categories', [ProjectController::class, 'getSubCategories'])->name('categories.sub');
    });

    // ==========================================
    // سرمایه‌گذاری (Investments)
    // ==========================================
    Route::prefix('najm-bahar/investments')->name('najm-bahar.investments.')->group(function () {
        // لیست پروژه‌های قابل سرمایه‌گذاری
        Route::get('/', [InvestmentController::class, 'index'])->name('index');
        Route::get('/projects/{project}', [InvestmentController::class, 'show'])->name('show');
        Route::post('/projects/{project}', [InvestmentController::class, 'store'])->name('store');
        
        // سرمایه‌گذاری‌های من
        Route::get('/my-investments', [InvestmentController::class, 'myInvestments'])->name('my-investments');
        Route::get('/my-investments/{investment}', [InvestmentController::class, 'showInvestment'])->name('show-investment');
        
        // پرداخت
        Route::get('/{investment}/payment', [InvestmentController::class, 'payment'])->name('payment');
        Route::post('/{investment}/payment', [InvestmentController::class, 'processPayment'])->name('process-payment');
        
        // لغو
        Route::post('/{investment}/cancel', [InvestmentController::class, 'cancel'])->name('cancel');
    });

    // ==========================================
    // پنل مدیریت (Admin)
    // ==========================================
    Route::middleware(['admin'])->prefix('admin/najm-bahar')->name('admin.najm-bahar.')->group(function () {
        // مدیریت پروژه‌ها
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [AdminProjectController::class, 'index'])->name('index');
            Route::get('/{project}', [AdminProjectController::class, 'show'])->name('show');
            Route::post('/{project}/start-review', [AdminProjectController::class, 'startReview'])->name('start-review');
            Route::post('/{project}/approve', [AdminProjectController::class, 'approve'])->name('approve');
            Route::post('/{project}/reject', [AdminProjectController::class, 'reject'])->name('reject');
            Route::post('/{project}/request-revision', [AdminProjectController::class, 'requestRevision'])->name('request-revision');
            Route::post('/{project}/archive', [AdminProjectController::class, 'archive'])->name('archive');
            Route::post('/{project}/assign', [AdminProjectController::class, 'assign'])->name('assign');
            Route::post('/{project}/update-assignment-review', [AdminProjectController::class, 'updateAssignmentReview'])->name('update-assignment-review');
        });

        // API endpoints for fetching users and groups
        Route::get('/get-users', [AdminProjectController::class, 'getUsers'])->name('get-users');
        Route::get('/get-groups', [AdminProjectController::class, 'getGroups'])->name('get-groups');
    });
});
