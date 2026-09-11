<?php

use App\Http\Controllers\Admin\DeploymentConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DeploymentConsoleController::class, 'index'])
    ->middleware('throttle:deployment-console-read')
    ->name('index');

Route::post('/run/{operation}', [DeploymentConsoleController::class, 'run'])
    ->middleware('throttle:deployment-console-write')
    ->whereIn('operation', [
        'migration_status',
        'reference_dry_run',
        'readiness',
        'flag_status',
        'migrate',
        'bootstrap',
        'reference_apply',
    ])
    ->name('run');
