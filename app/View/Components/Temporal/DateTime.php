<?php

namespace App\View\Components\Temporal;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class DateTime extends Component
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $contexts,
        public readonly DateTimeInterface|string $value,
        public readonly string $style = 'medium',
        public readonly ?string $timezone = null,
    ) {
    }

    public function display(): string
    {
        $context = $this->contexts->defaultContext();
        if ($this->timezone !== null && $this->timezone !== '') {
            $context = $this->contexts->forLocale(
                $context->locale(),
                $this->timezone,
                $context->numberingSystem(),
            );
        }

        return $this->temporal->dateTime($this->value, $context, $this->style);
    }

    public function machineValue(): string
    {
        $instant = $this->value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($this->value)
            : new DateTimeImmutable($this->value, new DateTimeZone('UTC'));

        return $instant
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function render(): View
    {
        return view('components.temporal.date-time');
    }
}
