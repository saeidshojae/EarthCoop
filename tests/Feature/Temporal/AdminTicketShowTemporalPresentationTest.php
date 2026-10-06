<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminTicketShowTemporalPresentationTest extends TestCase
{
    public function test_admin_ticket_show_uses_shared_temporal_components_for_all_ticket_dates(): void
    {
        $path = 'resources/views/admin/tickets/show.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertSame(1, substr_count($contents, '<x-temporal.date-time :value="$ticket->created_at"'));
        $this->assertSame(1, substr_count($contents, '<x-temporal.date-time :value="$ticket->updated_at"'));
        $this->assertSame(1, substr_count($contents, '<x-temporal.date-time :value="$comment->created_at"'));
        $this->assertSame(1, substr_count($contents, '<x-temporal.date :value="$ticket->created_at"'));
        $this->assertSame(1, substr_count($contents, '<x-temporal.time :value="$ticket->created_at"'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
