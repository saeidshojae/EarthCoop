<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class GroupMessageTemporalPresentationTest extends TestCase
{
    public function test_group_message_metadata_uses_temporal_presentation_boundary(): void
    {
        $path = 'resources/views/groups/partials/message.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertSame(4, substr_count($contents, '<x-temporal.date '));
        $this->assertSame(5, substr_count($contents, '<x-temporal.time '));
        $this->assertSame(4, substr_count($contents, 'style="long"'));
        $this->assertStringContainsString('TemporalService::class', $contents);
        $this->assertStringContainsString('TemporalContextResolver::class', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
