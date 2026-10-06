<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminNajmBaharAgreementIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_najm_bahar_agreement_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/najm-bahar/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
