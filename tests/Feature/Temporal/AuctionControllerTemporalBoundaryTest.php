<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AuctionControllerTemporalBoundaryTest extends TestCase
{
    public function test_auction_controller_does_not_call_jalalian_directly(): void
    {
        $contents = file_get_contents(base_path('app/Modules/Stock/Controllers/AuctionController.php'));

        $this->assertStringNotContainsString(
            'Jalalian::',
            $contents,
            'AuctionController must route Jalali parsing and formatting through TemporalService.',
        );
    }

    public function test_auction_controller_does_not_call_morilog_calendar_utils_directly(): void
    {
        $contents = file_get_contents(base_path('app/Modules/Stock/Controllers/AuctionController.php'));

        $this->assertStringNotContainsString(
            'CalendarUtils::',
            $contents,
            'AuctionController must route localized datetime parsing through TemporalService.',
        );
        $this->assertStringNotContainsString(
            'Morilog\\Jalali',
            $contents,
            'AuctionController must not depend directly on Morilog Jalali implementation details.',
        );
    }
}
