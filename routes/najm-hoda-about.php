<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/najm-hoda/about', function () {
    return view('najm-hoda.about');
})->name('najm-hoda.about');
