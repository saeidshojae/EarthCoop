<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminContentIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_content_index_uses_shared_temporal_date_components(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/content/index.blade.php'));

        $this->assertSame(3, substr_count($view, '<x-temporal.date :value="$date" />'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
