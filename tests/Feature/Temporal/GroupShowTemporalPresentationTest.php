<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class GroupShowTemporalPresentationTest extends TestCase
{
    public function test_group_dashboard_uses_shared_temporal_components(): void
    {
        $view = file_get_contents(base_path('resources/views/groups/show.blade.php'));

        $this->assertStringContainsString('<x-temporal.relative :value="$message->created_at" />', $view);
        $this->assertStringContainsString('<x-temporal.relative :value="$post->created_at" />', $view);
        $this->assertStringContainsString('<x-temporal.relative :value="$poll->created_at" />', $view);
        $this->assertStringContainsString('<x-temporal.relative :value="$election->created_at ?? now()" />', $view);

        $this->assertStringContainsString('<x-temporal.date :value="$poll->expires_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$election->starts_at" style="short" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$election->ends_at" style="short" />', $view);

        $this->assertStringNotContainsString('verta(', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
