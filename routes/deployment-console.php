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
        'iran_v1_v2_runtime_audit',
        'iran_v2_reference_dry_run',
        'iran_v2_reference_apply',
        'iran_v2_topology_dry_run',
        'topology_dry_run',
        'readiness',
        'flag_status',
        'migrate',
        'bootstrap',
        'stage_c_group_policy_apply',
        'reference_apply',
        'topology_apply',
    ])
    ->name('run');
