<?php

namespace App\View\Components\Temporal;

use App\Temporal\Context\TemporalContext;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class DateTimeInput extends Component
{
    public readonly TemporalContext $context;

    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $contexts,
        public readonly string $name,
        public readonly DateTimeInterface|string|null $value = null,
        public readonly ?string $id = null,
        public readonly bool $required = false,
    ) {
        $this->context = $this->contexts->defaultContext();
    }

    public function calendar(): string
    {
        return $this->context->calendar();
    }

    public function inputType(): string
    {
        return $this->calendar() === 'jalali' ? 'text' : 'datetime-local';
    }

    public function inputValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        if (is_string($this->value)) {
            $raw = trim($this->value);

            if ($this->looksCanonicalDateTime($raw)) {
                return $this->formatInstant(new DateTimeImmutable($raw, new DateTimeZone('UTC')));
            }

            try {
                $this->temporal->parseDateTime($raw, $this->context);

                return $raw;
            } catch (InvalidArgumentException|\ValueError) {
                return $raw;
            }
        }

        return $this->formatInstant($this->value);
    }

    public function resolvedId(): string
    {
        return $this->id ?: str_replace(['[', ']', '.'], ['_', '', '_'], $this->name);
    }

    public function render(): View
    {
        return view('components.temporal.date-time-input');
    }

    private function formatInstant(DateTimeInterface $instant): string
    {
        $formatted = $this->temporal->dateTime($instant, $this->context, 'short');

        return $this->calendar() === 'gregorian'
            ? str_replace(' ', 'T', $formatted)
            : $formatted;
    }

    private function looksCanonicalDateTime(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/', $value) === 1;
    }
}
