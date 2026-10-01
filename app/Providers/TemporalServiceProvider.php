<?php

namespace App\Providers;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Formatting\DigitFormatter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\TemporalManager;
use Illuminate\Support\ServiceProvider;

final class TemporalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DigitNormalizer::class);
        $this->app->singleton(DigitFormatter::class);
        $this->app->singleton(GregorianCalendarAdapter::class);
        $this->app->singleton(JalaliCalendarAdapter::class);

        $this->app->singleton(TemporalContextResolver::class, function (): TemporalContextResolver {
            return new TemporalContextResolver(
                (array) config('temporal.locale_calendars', []),
                (string) config('temporal.default_calendar', 'gregorian'),
                (string) config('temporal.default_timezone', 'UTC'),
                (string) config('app.locale', 'fa'),
                static fn (): string => app()->getLocale(),
            );
        });

        $this->app->bind(TemporalService::class, TemporalManager::class);
    }
}
