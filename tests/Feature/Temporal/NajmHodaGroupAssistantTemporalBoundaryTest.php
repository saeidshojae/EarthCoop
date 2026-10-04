<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmHodaGroupAssistantTemporalBoundaryTest extends TestCase
{
    public function test_group_assistant_auto_post_date_uses_temporal_service_boundary(): void
    {
        $path = 'app/Services/NajmHoda/NajmHodaGroupAssistantService.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('use App\\Temporal\\Contracts\\TemporalService;', $contents);
        $this->assertStringContainsString('protected TemporalService $temporal', $contents);
        $this->assertStringContainsString("$this->temporal->date(now(), style: 'short')", $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
