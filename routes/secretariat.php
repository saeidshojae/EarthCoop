<?php

use App\Http\Middleware\Authenticate;
use App\Modules\Secretariat\Controllers\SecretariatAccessController;
use App\Modules\Secretariat\Controllers\SecretariatCaseController;
use App\Modules\Secretariat\Controllers\SecretariatController;
use App\Modules\Secretariat\Controllers\SecretariatCorrespondenceController;
use App\Modules\Secretariat\Controllers\SecretariatDirectoryController;
use App\Modules\Secretariat\Controllers\SecretariatOfficeSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(Authenticate::class)->group(function () {
    Route::get('/secretariat', [SecretariatDirectoryController::class, 'index'])
        ->name('secretariat.directory');

    Route::get('/secretariat/central', [SecretariatDirectoryController::class, 'central'])
        ->name('secretariat.central');

    Route::get('/secretariat/groups/{group}', [SecretariatDirectoryController::class, 'group'])
        ->name('secretariat.group');

    Route::prefix('secretariat/offices/{office}')
        ->name('secretariat.')
        ->group(function () {
            Route::get('/', [SecretariatController::class, 'index'])->name('index');

            Route::get('/settings', [SecretariatOfficeSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SecretariatOfficeSettingsController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('settings.update');

            Route::get('/cases', [SecretariatCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/create', [SecretariatCaseController::class, 'create'])->name('cases.create');
            Route::post('/cases', [SecretariatCaseController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('cases.store');
            Route::get('/cases/{case}', [SecretariatCaseController::class, 'show'])->name('cases.show');
            Route::post('/cases/{case}/records', [SecretariatCaseController::class, 'addRecord'])
                ->middleware('throttle:20,1')
                ->name('cases.records.store');
            Route::post('/cases/{case}/references', [SecretariatCaseController::class, 'addCrossOfficeReference'])
                ->middleware('throttle:20,1')
                ->name('cases.references.store');
            Route::post('/cases/{case}/transition', [SecretariatCaseController::class, 'transition'])
                ->middleware('throttle:20,1')
                ->name('cases.transition');

            Route::get('/records/create', [SecretariatController::class, 'create'])->name('records.create');
            Route::post('/records', [SecretariatController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('records.store');

            Route::get('/correspondence/create', [SecretariatCorrespondenceController::class, 'create'])
                ->name('correspondence.create');
            Route::post('/correspondence', [SecretariatCorrespondenceController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('correspondence.store');
            Route::get('/records/{record}/correspondence', [SecretariatCorrespondenceController::class, 'show'])
                ->name('correspondence.show');

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

            Route::post('/records/{record}/dispatches', [SecretariatCorrespondenceController::class, 'dispatch'])
                ->middleware('throttle:20,1')
                ->name('dispatches.store');
            Route::post('/records/{record}/dispatches/{dispatch}/transition', [SecretariatCorrespondenceController::class, 'transitionDispatch'])
                ->middleware('throttle:30,1')
                ->name('dispatches.transition');

            Route::get('/records/{record}/access', [SecretariatAccessController::class, 'index'])
                ->name('acl.index');
            Route::post('/records/{record}/acl', [SecretariatAccessController::class, 'grant'])
                ->middleware('throttle:20,1')
                ->name('acl.grant');
            Route::delete('/records/{record}/acl/{aclEntry}', [SecretariatAccessController::class, 'revoke'])
                ->middleware('throttle:20,1')
                ->name('acl.revoke');
        });
});
