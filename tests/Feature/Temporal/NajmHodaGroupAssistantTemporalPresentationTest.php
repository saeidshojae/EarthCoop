<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmHodaGroupAssistantTemporalPresentationTest extends TestCase
{
    public function test_auto_post_draft_date_uses_temporal_boundary(): void
    {
        $path = 'app/Services/NajmHoda/NajmHodaGroupAssistantService.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('TemporalService::class', $contents);
        $this->assertStringContainsString('TemporalContextResolver::class', $contents);
        $this->assertStringContainsString("->date(now(), $temporalContext, 'short')", $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
