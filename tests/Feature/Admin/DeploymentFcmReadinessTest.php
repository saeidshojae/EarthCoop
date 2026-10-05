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

    public function test_command_reports_safe_failure_when_configuration_is_absent(): void
    {
        config()->set('services.push.fcm.project_id', null);
        config()->set('services.push.fcm.credentials', null);
        $this->artisan('deployment:fcm-readiness')
            ->expectsOutput('{"ready":false,"code":"fcm_credentials_invalid"}')
            ->assertExitCode(1);
    }
}
