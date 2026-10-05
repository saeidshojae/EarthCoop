<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class UserTicketShowTemporalPresentationTest extends TestCase
{
    public function test_user_ticket_detail_uses_shared_temporal_components(): void
    {
        $contents = file_get_contents(resource_path('views/user/tickets/show.blade.php'));

        $this->assertSame(1, substr_count($contents, '<x-temporal.date-time :value="$ticket->created_at"'));
        $this->assertSame(1, substr_count($contents, '<x-temporal.date-time :value="$comment->created_at"'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
