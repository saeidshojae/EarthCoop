<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharReportsPdfTemporalPresentationTest extends TestCase
{
    public function test_report_pdf_uses_shared_temporal_components(): void
    {
        $view = file_get_contents(base_path('resources/views/najm-bahar/reports/pdf.blade.php'));

        $this->assertStringContainsString('<x-temporal.date :value="$dateFrom" />', $view);
        $this->assertStringContainsString('<x-temporal.date :value="$dateTo" />', $view);
        $this->assertStringContainsString('<x-temporal.date-time :value="$transaction->created_at" />', $view);
        $this->assertStringContainsString('<x-temporal.date-time :value="now()" />', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
