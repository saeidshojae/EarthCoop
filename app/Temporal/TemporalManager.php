<?php

namespace App\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Formatting\DigitFormatter;
use App\Temporal\Formatting\DigitNormalizer;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class TemporalManager implements TemporalService
{
    private readonly DigitFormatter $digitFormatter;
    private readonly DigitNormalizer $digitNormalizer;

    public function __construct(
        private readonly GregorianCalendarAdapter $gregorian,
        private readonly JalaliCalendarAdapter $jalali,
        private readonly TemporalContextResolver $contexts,
        ?DigitFormatter $digitFormatter = null,
        ?DigitNormalizer $digitNormalizer = null,
    ) {
        $this->digitFormatter = $digitFormatter ?? new DigitFormatter();
        $this->digitNormalizer = $digitNormalizer ?? new DigitNormalizer();
    }

    public function date(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null, string $style = 'medium'): string
    {
        $context ??= $this->contexts->defaultContext();
        $date = $this->toLocalDate($value, $context);
        $formatted = $this->adapter($context)->formatDate($date, $style, $context->locale());

        return $this->shapeDigits($formatted, $context);
    }

    public function dateTime(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'medium'): string
    {
        $context ??= $this->contexts->defaultContext();
        $instant = $this->toInstant($value)->setTimezone(new DateTimeZone($context->timezone()));
        $date = LocalDate::fromCanonical($instant->format('Y-m-d'));
        $formatted = $this->adapter($context)->formatDate($date, $style, $context->locale()) . ' ' . $instant->format('H:i');

        return $this->shapeDigits($formatted, $context);
    }

    public function time(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'short'): string
    {
        $context ??= $this->contexts->defaultContext();
        $instant = $this->toInstant($value)->setTimezone(new DateTimeZone($context->timezone()));
        $formatted = match ($style) {
            'short' => $instant->format('H:i'),
            'long' => $instant->format('H:i:s'),
            default => throw new InvalidArgumentException("Unsupported time style: {$style}"),
        };

        return $this->shapeDigits($formatted, $context);
    }

    public function relative(DateTimeInterface|string $value, ?TemporalContext $context = null): string
    {
        $context ??= $this->contexts->defaultContext();
        $target = $this->toInstant($value);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $seconds = $target->getTimestamp() - $now->getTimestamp();
        $absolute = abs($seconds);
        $isPersian = $this->baseLocale($context->locale()) === 'fa';

        if ($absolute < 60) {
            $amount = max(1, $absolute);
            $unit = $isPersian ? 'ثانیه' : 'second';
        } elseif ($absolute < 3600) {
            $amount = intdiv($absolute, 60);
            $unit = $isPersian ? 'دقیقه' : 'minute';
        } elseif ($absolute < 86400) {
            $amount = intdiv($absolute, 3600);
            $unit = $isPersian ? 'ساعت' : 'hour';
        } else {
            $amount = intdiv($absolute, 86400);
            $unit = $isPersian ? 'روز' : 'day';
        }

        if (! $isPersian && $amount !== 1) {
            $unit .= 's';
        }

        $formatted = $isPersian
            ? ($seconds < 0 ? "{$amount} {$unit} پیش" : "{$amount} {$unit} دیگر")
            : ($seconds < 0 ? "{$amount} {$unit} ago" : "in {$amount} {$unit}");

        return $this->shapeDigits($formatted, $context);
    }

    public function year(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null): int
    {
        $context ??= $this->contexts->defaultContext();
        $date = $this->toLocalDate($value, $context);

        return $this->adapter($context)->year($date);
    }

    public function parseDate(string $value, ?TemporalContext $context = null): LocalDate
    {
        $context ??= $this->contexts->defaultContext();

        return $this->adapter($context)->parseDate($value);
    }

    public function parseDateTime(string $value, ?TemporalContext $context = null): DateTimeImmutable
    {
        $context ??= $this->contexts->defaultContext();
        $normalized = trim($this->digitNormalizer->toLatin($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?$/', $normalized) === 1) {
            $hasExplicitZone = preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $normalized) === 1;
            $canonical = new DateTimeImmutable(
                $normalized,
                new DateTimeZone($hasExplicitZone ? 'UTC' : $context->timezone()),
            );

            return $canonical->setTimezone(new DateTimeZone('UTC'));
        }

        if (! preg_match('/^(.+?)[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Localized datetime must contain a date and HH:MM time.');
        }

        $date = $this->adapter($context)->parseDate(trim($matches[1]));
        $hour = (int) $matches[2];
        $minute = (int) $matches[3];
        $second = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('Localized datetime contains an invalid time.');
        }

        $local = new DateTimeImmutable(
            sprintf('%s %02d:%02d:%02d', $date->toCanonical(), $hour, $minute, $second),
            new DateTimeZone($context->timezone()),
        );

        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    public function parseDateParts(int $day, int $month, int $year, ?TemporalContext $context = null): LocalDate
    {
        $context ??= $this->contexts->defaultContext();

        $value = match ($context->calendar()) {
            'jalali' => sprintf('%04d/%02d/%02d', $year, $month, $day),
            'gregorian' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            default => throw new InvalidArgumentException("Unsupported calendar: {$context->calendar()}"),
        };

        return $this->adapter($context)->parseDate($value);
    }

    public function startOfDay(LocalDate $date, ?TemporalContext $context = null): DateTimeImmutable
    {
        $context ??= $this->contexts->defaultContext();
        $local = new DateTimeImmutable(
            $date->toCanonical() . ' 00:00:00',
            new DateTimeZone($context->timezone()),
        );

        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    public function endOfDay(LocalDate $date, ?TemporalContext $context = null): DateTimeImmutable
    {
        $context ??= $this->contexts->defaultContext();
        $local = new DateTimeImmutable(
            $date->toCanonical() . ' 23:59:59.999999',
            new DateTimeZone($context->timezone()),
        );

        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    private function adapter(TemporalContext $context): CalendarAdapter
    {
        return match ($context->calendar()) {
            'jalali' => $this->jalali,
            'gregorian' => $this->gregorian,
            default => throw new InvalidArgumentException("Unsupported calendar: {$context->calendar()}"),
        };
    }

    private function toLocalDate(DateTimeInterface|LocalDate|string $value, TemporalContext $context): LocalDate
    {
        if ($value instanceof LocalDate) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return LocalDate::fromCanonical($value);
        }

        $instant = $this->toInstant($value)->setTimezone(new DateTimeZone($context->timezone()));

        return LocalDate::fromCanonical($instant->format('Y-m-d'));
    }

    private function toInstant(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'));
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function shapeDigits(string $value, TemporalContext $context): string
    {
        return $this->digitFormatter->format($value, $context->numberingSystem());
    }

    private function baseLocale(string $locale): string
    {
        return strtolower((string) preg_split('/[-_]/', $locale, 2)[0]);
    }
}
