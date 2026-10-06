<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharReportsIndexTemporalPresentationTest extends TestCase
{
    public function test_report_index_uses_shared_temporal_datetime_components(): void
    {
        $view = file_get_contents(base_path('resources/views/najm-bahar/reports/index.blade.php'));

        $this->assertSame(2, substr_count($view, '<x-temporal.date-time :value="$transaction->created_at" />'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
