<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class PublicLegalTemporalPresentationTest extends TestCase
{
    public function test_terms_acceptance_timestamp_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/terms.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }

    public function test_najm_bahar_agreement_update_date_uses_temporal_date_component(): void
    {
        $path = 'resources/views/najm-bahar/agreement.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
