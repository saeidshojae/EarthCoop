<?php

namespace App\Temporal\Context;

use App\Models\User;
use DateTimeZone;
use Throwable;

final class TemporalContextResolver
{
    public function __construct(
        private readonly array $localeCalendars,
        private readonly string $defaultCalendar = 'gregorian',
        private readonly string $defaultTimezone = 'UTC',
        private readonly string $defaultLocale = 'fa',
    ) {
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
        return $this->forLocale(
            $localeOverride ?? $this->defaultLocale,
            $timezoneOverride,
        );
    }

    public function forRecipient(
        User $recipient,
        ?string $localeOverride = null,
        ?string $timezoneOverride = null,
    ): TemporalContext {
        return $this->forUser($recipient, $localeOverride, $timezoneOverride);
    }

    public function defaultContext(): TemporalContext
    {
        return $this->forLocale($this->defaultLocale, $this->defaultTimezone);
    }

    private function resolveTimezone(?string $timezone): string
    {
        $candidate = $timezone ?: $this->defaultTimezone;

        try {
            new DateTimeZone($candidate);

            return $candidate;
        } catch (Throwable) {
            return $this->defaultTimezone;
        }
    }
}
