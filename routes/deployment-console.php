<?php

use App\Http\Controllers\Admin\DeploymentConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DeploymentConsoleController::class, 'index'])->name('index');
