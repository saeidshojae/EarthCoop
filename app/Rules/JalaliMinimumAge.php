<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Morilog\Jalali\Jalalian;

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
            $birthDate = Jalalian::fromFormat('Y-m-d', $value)->toCarbon();
            $minAllowed = Jalalian::now()->subYears($this->minAge)->toCarbon();

            return $birthDate->lte($minAllowed); // ✅ حالا درست کار می‌کنه
        } catch (\Exception $e) {
            return false;
        }
    }

    public function message()
    {
        return "سن شما باید حداقل {$this->minAge} سال باشد.";
    }
}
