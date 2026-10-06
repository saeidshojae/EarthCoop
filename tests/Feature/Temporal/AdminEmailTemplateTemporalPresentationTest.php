<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminEmailTemplateTemporalPresentationTest extends TestCase
{
    public function test_admin_email_template_index_uses_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/emails/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
