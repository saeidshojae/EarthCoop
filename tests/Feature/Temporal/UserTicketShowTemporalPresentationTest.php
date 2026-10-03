<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class UserTicketShowTemporalPresentationTest extends TestCase
{
    public function test_user_ticket_show_uses_temporal_presentation_components(): void
    {
        $path = 'resources/views/user/tickets/show.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
