<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class GroupCommentTemporalPresentationTest extends TestCase
{
    public function test_group_comment_partial_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/groups/partials/comment.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
