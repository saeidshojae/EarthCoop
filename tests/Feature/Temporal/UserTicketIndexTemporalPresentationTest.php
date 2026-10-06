<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class UserTicketIndexTemporalPresentationTest extends TestCase
{
    public function test_user_ticket_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/user/tickets/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
