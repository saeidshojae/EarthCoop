<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class HistoryIndexBaseTemporalPresentationTest extends TestCase
{
    public function test_history_index_uses_shared_temporal_components(): void
    {
        $view = file_get_contents(base_path('resources/views/history/index-base.blade.php'));

        $this->assertStringContainsString('<x-temporal.date :value="$blog->created_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date-time :value="$tx->created_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$comment->created_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$reply->created_at" style="short" />', $view);
        $this->assertStringContainsString('@if($reaction->created_at)', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$reaction->created_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$poll->created_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$election->created_at" style="short" />', $view);

        $this->assertSame(6, substr_count($view, '<x-temporal.date '));
        $this->assertSame(1, substr_count($view, '<x-temporal.date-time '));
        $this->assertStringNotContainsString('verta(', $view);
    }
}
