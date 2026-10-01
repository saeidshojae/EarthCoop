<?php

namespace App\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Formatting\DigitFormatter;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class TemporalManager implements TemporalService
{
    private readonly DigitFormatter $digitFormatter;

    public function __construct(
        private readonly GregorianCalendarAdapter $gregorian,
        private readonly JalaliCalendarAdapter $jalali,
        private readonly TemporalContextResolver $contexts,
        ?DigitFormatter $digitFormatter = null,
    ) {
        $this->digitFormatter = $digitFormatter ?? new DigitFormatter();
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
            $unit = $isPersian ? 'ثانیه' : 'seconds';
        } elseif ($absolute < 3600) {
            $amount = intdiv($absolute, 60);
            $unit = $isPersian ? 'دقیقه' : 'minutes';
        } elseif ($absolute < 86400) {
            $amount = intdiv($absolute, 3600);
            $unit = $isPersian ? 'ساعت' : 'hours';
        } else {
            $amount = intdiv($absolute, 86400);
            $unit = $isPersian ? 'روز' : 'days';
        }

        $formatted = $isPersian
            ? ($seconds < 0 ? "{$amount} {$unit} پیش" : "{$amount} {$unit} دیگر")
            : ($seconds < 0 ? "{$amount} {$unit} ago" : "in {$amount} {$unit}");

        return $this->shapeDigits($formatted, $context);
    }

    public function parseDate(string $value, ?TemporalContext $context = null): LocalDate
    {
        $context ??= $this->contexts->defaultContext();

        return $this->adapter($context)->parseDate($value);
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
