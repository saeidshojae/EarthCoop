<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class PollHistoryTemporalPresentationTest extends TestCase
{
    public function test_poll_history_created_date_uses_temporal_date_component(): void
    {
        $path = 'resources/views/history/poll.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
