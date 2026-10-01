<?php

namespace Tests\Unit\Temporal;

use App\Temporal\Context\TemporalContext;
use InvalidArgumentException;
use Tests\TestCase;

class TemporalContextTest extends TestCase
{
    public function test_temporal_config_defines_locale_calendar_policy(): void
    {
        $this->assertSame('UTC', config('temporal.storage_timezone'));
        $this->assertSame('jalali', config('temporal.locale_calendars.fa'));
        $this->assertSame('gregorian', config('temporal.locale_calendars.en'));
        $this->assertSame('gregorian', config('temporal.locale_calendars.ar'));
        $this->assertSame('gregorian', config('temporal.default_calendar'));
    }

    public function test_context_is_created_with_explicit_immutable_values(): void
    {
        $context = TemporalContext::create('fa', 'jalali', 'Asia/Tehran', 'persian');

        $this->assertSame('fa', $context->locale());
        $this->assertSame('jalali', $context->calendar());
        $this->assertSame('Asia/Tehran', $context->timezone());
        $this->assertSame('persian', $context->numberingSystem());
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemporalContext::create('fa', 'jalali', 'Not/A_Timezone', 'persian');
    }
}
