<?php

namespace App\View\Components\Temporal;

use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class DateInput extends Component
{
    public readonly TemporalContext $context;

    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $contexts,
        public readonly string $name,
        public readonly DateTimeInterface|LocalDate|string|null $value = null,
        public readonly ?string $id = null,
        public readonly bool $required = false,
        public readonly bool $disabled = false,
    ) {
        $this->context = $this->contexts->defaultContext();
    }

    public function calendar(): string
    {
        return $this->context->calendar();
    }

    public function inputType(): string
    {
        return $this->calendar() === 'jalali' ? 'text' : 'date';
    }

    public function inputValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        if ($this->value instanceof LocalDate) {
            return $this->calendar() === 'gregorian'
                ? $this->value->toCanonical()
                : $this->temporal->date($this->value, $this->context, 'short');
        }

        if (is_string($this->value)) {
            $value = trim($this->value);

            if ($this->calendar() === 'gregorian' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return $value;
            }

            if (
                $this->calendar() === 'jalali'
                && preg_match('/^[۰-۹٠-٩0-9]{4}\/[۰-۹٠-٩0-9]{2}\/[۰-۹٠-٩0-9]{2}$/u', $value) === 1
            ) {
                return $value;
            }
        }

        return $this->temporal->date($this->value, $this->context, 'short');
    }

    public function resolvedId(): string
    {
        return $this->id ?: str_replace(['[', ']', '.'], ['_', '', '_'], $this->name);
    }

    public function render(): View
    {
        return view('components.temporal.date-input');
    }
}
