<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class TicketTemporalPresentationTest extends TestCase
{
    public function test_ticket_email_views_use_temporal_datetime_component(): void
    {
        foreach ([
            'resources/views/emails/ticket-created.blade.php',
            'resources/views/emails/ticket-reply.blade.php',
        ] as $path) {
            $contents = file_get_contents(base_path($path));

            $this->assertStringContainsString('<x-temporal.date-time', $contents, $path);
            $this->assertStringNotContainsString('Morilog\\Jalali', $contents, $path);
            $this->assertStringNotContainsString('Jalalian::', $contents, $path);
        }
    }
}
