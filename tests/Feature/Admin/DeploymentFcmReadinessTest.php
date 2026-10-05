<?php

namespace Tests\Feature\Admin;

use App\Services\Deployment\DeploymentConsoleService;
use Tests\TestCase;

class DeploymentFcmReadinessTest extends TestCase
{
    public function test_diagnostic_is_fixed_read_only_console_operation(): void
    {
        $operation = app(DeploymentConsoleService::class)->operations()['fcm_readiness'] ?? null;
        $this->assertSame(['command' => 'deployment:fcm-readiness', 'arguments' => [], 'write' => false, 'confirmation' => null], $operation);
        $this->assertStringContainsString("'fcm_readiness'", file_get_contents(base_path('routes/deployment-console.php')));
    }
}
