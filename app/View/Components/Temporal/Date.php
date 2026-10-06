<?php

namespace App\View\Components\Temporal;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Date extends Component
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $contexts,
        public readonly DateTimeInterface|LocalDate|string $value,
        public readonly string $style = 'medium',
    ) {
    }

    public function display(): string
    {
        return $this->temporal->date($this->value, $this->contexts->defaultContext(), $this->style);
    }

    public function machineValue(): string
    {
        if ($this->value instanceof LocalDate) {
            return $this->value->toCanonical();
        }

        if (is_string($this->value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->value)) {
            return LocalDate::fromCanonical($this->value)->toCanonical();
        }

        $context = $this->contexts->defaultContext();
        $instant = $this->value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($this->value)
            : new DateTimeImmutable($this->value, new DateTimeZone('UTC'));

        return $instant
            ->setTimezone(new DateTimeZone($context->timezone()))
            ->format('Y-m-d');
    }

    public function render(): View
    {
        return view('components.temporal.date');
    }
}
