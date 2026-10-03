<?php

namespace App\Rules;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Policies\AgePolicy;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\Rule;
use Throwable;

class JalaliMinimumAge implements Rule
{
    protected $minAge;

    public function __construct($minAge = 15)
    {
        $this->minAge = $minAge;
    }

    public function passes($attribute, $value)
    {
        try {
            $contexts = app(TemporalContextResolver::class);
            $temporal = app(TemporalService::class);
            $agePolicy = app(AgePolicy::class);
            $context = $contexts->forLocale('fa');
            $birthDate = $temporal->parseDate(
                str_replace('-', '/', (string) $value),
                $context,
            );
            $today = new DateTimeImmutable('today', new DateTimeZone($context->timezone()));

            return $agePolicy->meetsMinimumAge($birthDate, (int) $this->minAge, $today);
        } catch (Throwable) {
            return false;
        }
    }

    public function message()
    {
        return "سن شما باید حداقل {$this->minAge} سال باشد.";
    }
}
