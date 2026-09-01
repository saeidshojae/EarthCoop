<?php

namespace Tests\Feature\NajmBahar;

use Tests\TestCase;

class NajmBaharSchedulerContractTest extends TestCase
{
    public function test_due_scheduled_transactions_are_wired_into_the_application_scheduler(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($consoleRoutes);
        $this->assertStringContainsString(
            "Schedule::command('najm-bahar:process-scheduled')",
            $consoleRoutes,
            'The scheduled-transaction executor must be wired into the application scheduler.'
        );
        $this->assertStringContainsString('->withoutOverlapping()', $consoleRoutes);
    }
}
