<?php

namespace Tests\Unit\Temporal;

use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use PHPUnit\Framework\TestCase;

class TemporalContextResolverTest extends TestCase
{
    private function resolver(): TemporalContextResolver
    {
        return new TemporalContextResolver(
            ['fa' => 'jalali', 'en' => 'gregorian', 'ar' => 'gregorian'],
            'gregorian',
            'UTC',
            'fa',
        );
    }

    public function test_persian_locale_resolves_jalali_and_persian_digits(): void
    {
        $context = $this->resolver()->forLocale('fa', 'Asia/Tehran');

        $this->assertSame('jalali', $context->calendar());
        $this->assertSame('persian', $context->numberingSystem());
        $this->assertSame('Asia/Tehran', $context->timezone());
    }

    public function test_locale_variants_use_base_locale_calendar_policy(): void
    {
        $this->assertSame('jalali', $this->resolver()->forLocale('fa-IR', 'Asia/Tehran')->calendar());
        $this->assertSame('gregorian', $this->resolver()->forLocale('en-GB', 'Europe/London')->calendar());
    }

    public function test_arabic_defaults_to_gregorian(): void
    {
        $context = $this->resolver()->forLocale('ar', 'Asia/Riyadh');

        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('latin', $context->numberingSystem());
    }

    public function test_invalid_timezone_falls_back_to_configured_default(): void
    {
        $this->assertSame('UTC', $this->resolver()->forLocale('en', 'Not/A_Timezone')->timezone());
    }

    public function test_recipient_override_is_explicit_and_not_process_locale_dependent(): void
    {
        $context = $this->resolver()->forRecipient(new User(), 'en', 'Europe/London');

        $this->assertSame('en', $context->locale());
        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('Europe/London', $context->timezone());
    }
}
