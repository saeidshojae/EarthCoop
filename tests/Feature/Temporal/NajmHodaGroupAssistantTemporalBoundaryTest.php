<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmHodaGroupAssistantTemporalBoundaryTest extends TestCase
{
    public function test_group_assistant_routes_calendar_formatting_through_temporal_service(): void
    {
        $path = 'app/Services/NajmHoda/NajmHodaGroupAssistantService.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('App\\Temporal\\Contracts\\TemporalService', $contents);
        $this->assertStringContainsString('$this->temporal->date(', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
