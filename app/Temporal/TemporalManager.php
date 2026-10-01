<?php

namespace App\Temporal;

use App\Temporal\Calendars\GregorianCalendarAdapter;
use App\Temporal\Calendars\JalaliCalendarAdapter;
use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\CalendarAdapter;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class TemporalManager implements TemporalService
{
    public function __construct(
        private readonly GregorianCalendarAdapter $gregorian,
        private readonly JalaliCalendarAdapter $jalali,
        private readonly TemporalContextResolver $contexts,
    ) {
    }

    public function date(DateTimeInterface|LocalDate|string $value, ?TemporalContext $context = null, string $style = 'medium'): string
    {
        $context ??= $this->contexts->defaultContext();
        $date = $this->toLocalDate($value, $context);

        return $this->adapter($context)->formatDate($date, $style, $context->locale());
    }

    public function dateTime(DateTimeInterface|string $value, ?TemporalContext $context = null, string $style = 'medium'): string
    {
        $context ??= $this->contexts->defaultContext();
        $instant = $this->toInstant($value)->setTimezone(new DateTimeZone($context->timezone()));
        $date = LocalDate::fromCanonical($instant->format('Y-m-d'));

        return $this->adapter($context)->formatDate($date, $style, $context->locale()) . ' ' . $instant->format('H:i');
    }

    public function relative(DateTimeInterface|string $value, ?TemporalContext $context = null): string
    {
        $context ??= $this->contexts->defaultContext();
        $target = $this->toInstant($value);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $seconds = $target->getTimestamp() - $now->getTimestamp();
        $absolute = abs($seconds);

        if ($absolute < 60) {
            $amount = max(1, $absolute);
            $unit = $context->locale() === 'fa' ? 'ثانیه' : 'seconds';
        } elseif ($absolute < 3600) {
            $amount = intdiv($absolute, 60);
            $unit = $context->locale() === 'fa' ? 'دقیقه' : 'minutes';
        } elseif ($absolute < 86400) {
            $amount = intdiv($absolute, 3600);
            $unit = $context->locale() === 'fa' ? 'ساعت' : 'hours';
        } else {
            $amount = intdiv($absolute, 86400);
            $unit = $context->locale() === 'fa' ? 'روز' : 'days';
        }

        if ($context->locale() === 'fa') {
            return $seconds < 0 ? "{$amount} {$unit} پیش" : "{$amount} {$unit} دیگر";
        }

        return $seconds < 0 ? "{$amount} {$unit} ago" : "in {$amount} {$unit}";
    }

    public function parseDate(string $value, ?TemporalContext $context = null): LocalDate
    {
        $context ??= $this->contexts->defaultContext();

        return $this->adapter($context)->parseDate($value);
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
}
