<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharReportPdfTemporalPresentationTest extends TestCase
{
    public function test_najm_bahar_report_pdf_uses_temporal_presentation_components(): void
    {
        $path = 'resources/views/najm-bahar/reports/pdf.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Carbon\\Carbon::parse', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
