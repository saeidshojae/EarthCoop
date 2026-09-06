<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationDuplicateRaceContractTest extends TestCase
{
    public function test_event_key_unique_race_has_explicit_graceful_duplicate_handling(): void
    {
        $source = file_get_contents(app_path('Services/ReputationService.php'));

        $this->assertStringContainsString('catch (QueryException $e)', $source);
        $this->assertStringContainsString("in_array((string) \$e->getCode(), ['23000', '23505'], true)", $source);
        $this->assertStringContainsString("UserPointTransaction::where('event_key', \$eventKey)->exists()", $source);
        $this->assertStringContainsString('throw $e;', $source);
    }
}
