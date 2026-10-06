<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class ReputationHistoryTemporalPresentationTest extends TestCase
{
    public function test_reputation_history_timestamps_use_temporal_datetime_components(): void
    {
        $path = 'resources/views/history/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertSame(2, substr_count($contents, '<x-temporal.date-time'));
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
