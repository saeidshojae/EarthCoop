<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class GroupCommentPageTemporalPresentationTest extends TestCase
{
    public function test_group_comment_page_uses_shared_temporal_datetime_component(): void
    {
        $view = file_get_contents(base_path('resources/views/groups/comment.blade.php'));

        $this->assertStringContainsString('<x-temporal.date-time :value="$blog->created_at" />', $view);
        $this->assertStringNotContainsString('verta(', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
