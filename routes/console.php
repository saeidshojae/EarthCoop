<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the policy evaluator daily. MonetaryService idempotency ensures that a
// monthly/quarterly/yearly activation can only affect each account once per
// policy period, including scheduler retries.
Schedule::command('najm-bahar:activate-faded')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Retry only already-failed monetary operations that are due under the bounded
// backoff policy. Dead-letter recovery is intentionally never scheduled and
// remains an explicit operator action after reviewing the monetary report.
Schedule::command('najm-bahar:retry-failed-monetary-operations --limit=20')
    ->everyFiveMinutes()
    ->withoutOverlapping();
