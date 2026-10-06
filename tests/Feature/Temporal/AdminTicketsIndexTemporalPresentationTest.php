<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminTicketsIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_tickets_index_uses_temporal_component_for_ticket_created_at(): void
    {
        $path = 'resources/views/admin/tickets/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time :value="$ticket->created_at"', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
