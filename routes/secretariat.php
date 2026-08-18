<?php

use App\Http\Middleware\Authenticate;
use App\Modules\Secretariat\Controllers\SecretariatController;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)
    ->prefix('secretariat/offices/{office}')
    ->name('secretariat.')
    ->group(function () {
        Route::get('/', [SecretariatController::class, 'index'])->name('index');
        Route::get('/records/create', [SecretariatController::class, 'create'])->name('records.create');
        Route::post('/records', [SecretariatController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('records.store');
        Route::get('/records/{record}', [SecretariatController::class, 'show'])->name('records.show');
        Route::post('/records/{record}/submit', [SecretariatController::class, 'submit'])
            ->middleware('throttle:20,1')
            ->name('records.submit');
        Route::post('/records/{record}/register', [SecretariatController::class, 'register'])
            ->middleware('throttle:10,1')
            ->name('records.register');
        Route::post('/records/{record}/attachments', [SecretariatController::class, 'upload'])
            ->middleware('throttle:10,1')
            ->name('attachments.store');
        Route::get('/records/{record}/attachments/{attachment}', [SecretariatController::class, 'download'])
            ->name('attachments.download');
        Route::post('/records/{record}/relations', [SecretariatController::class, 'addRelation'])
            ->middleware('throttle:20,1')
            ->name('relations.store');
    });
