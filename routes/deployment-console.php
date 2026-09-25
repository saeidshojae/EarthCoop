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
        'topology_dry_run',
        'readiness',
        'flag_status',
        'iran_v1_v2_audit',
        'reference_v2_dry_run',
        'settlement_v2_dry_run',
        'topology_v2_dry_run',
        'migrate',
        'bootstrap',
        'stage_c_group_policy_apply',
        'reference_apply',
        'topology_apply',
        'reference_v2_apply',
        'settlement_v2_apply',
        'topology_v2_apply',
    ])
    ->name('run');
