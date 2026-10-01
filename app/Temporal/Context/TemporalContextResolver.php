<?php

namespace App\Temporal\Context;

use App\Models\User;
use Closure;
use DateTimeZone;
use Throwable;

final class TemporalContextResolver
{
    private readonly Closure $activeLocaleResolver;

    public function __construct(
        private readonly array $localeCalendars,
        private readonly string $defaultCalendar = 'gregorian',
        private readonly string $defaultTimezone = 'UTC',
        private readonly string $defaultLocale = 'fa',
        ?Closure $activeLocaleResolver = null,
    ) {
        $this->activeLocaleResolver = $activeLocaleResolver
            ?? static fn (): string => $defaultLocale;
    }

    public function forLocale(
        string $locale,
        ?string $timezone = null,
        ?string $numberingSystem = null,
    ): TemporalContext {
        $baseLocale = strtolower((string) preg_split('/[-_]/', $locale, 2)[0]);
        $calendar = $this->localeCalendars[$baseLocale] ?? $this->defaultCalendar;
        $resolvedTimezone = $this->resolveTimezone($timezone);
        $resolvedNumberingSystem = $numberingSystem ?? ($baseLocale === 'fa' ? 'persian' : 'latin');

        return TemporalContext::create(
            $locale,
            $calendar,
            $resolvedTimezone,
            $resolvedNumberingSystem,
        );
    }

    public function forUser(
        ?User $user,
        ?string $localeOverride = null,
        ?string $timezoneOverride = null,
    ): TemporalContext {
        $locale = $localeOverride
            ?? $this->validStoredLocale($user?->locale)
            ?? $this->activeLocale();
        $timezone = $timezoneOverride
            ?? $this->validStoredTimezone($user?->timezone)
            ?? $this->defaultTimezone;

        return $this->forLocale($locale, $timezone);
    }

    public function forRecipient(
        User $recipient,
        ?string $localeOverride = null,
        ?string $timezoneOverride = null,
    ): TemporalContext {
        $locale = $localeOverride
            ?? $this->validStoredLocale($recipient->locale)
            ?? $this->defaultLocale;
        $timezone = $timezoneOverride
            ?? $this->validStoredTimezone($recipient->timezone)
            ?? $this->defaultTimezone;

        return $this->forLocale($locale, $timezone);
    }

    public function defaultContext(): TemporalContext
    {
        return $this->forLocale($this->activeLocale(), $this->defaultTimezone);
    }

    private function activeLocale(): string
    {
        $locale = ($this->activeLocaleResolver)();

        return is_string($locale) && $locale !== '' ? $locale : $this->defaultLocale;
    }

    private function validStoredLocale(mixed $locale): ?string
    {
        if (! is_string($locale) || $locale === '') {
            return null;
        }

        $baseLocale = strtolower((string) preg_split('/[-_]/', $locale, 2)[0]);

        return array_key_exists($baseLocale, $this->localeCalendars) ? $locale : null;
    }

    private function validStoredTimezone(mixed $timezone): ?string
    {
        if (! is_string($timezone) || $timezone === '') {
            return null;
        }

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveTimezone(?string $timezone): string
    {
        return $this->validStoredTimezone($timezone) ?? $this->defaultTimezone;
    }
}
