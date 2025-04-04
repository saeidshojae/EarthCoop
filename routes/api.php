<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OccupationalFieldController;
use App\Http\Controllers\ExperienceFieldController;

Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});

Route::get('/locations', [LocationController::class, 'getLocations']);
Route::get('/occupational-fields', [OccupationalFieldController::class, 'getFields']);
Route::get('/experience-fields', [ExperienceFieldController::class, 'getFields']);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
