<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminHoldingTemporalPresentationTest extends TestCase
{
    public function test_admin_holding_transaction_timestamps_use_temporal_component(): void
    {
        $path = 'resources/views/Stock/admin_holdings_show.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
    }
}
