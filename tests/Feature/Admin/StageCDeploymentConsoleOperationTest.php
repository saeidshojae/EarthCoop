<?php

namespace Tests\Feature\Admin;

use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StageCDeploymentConsoleOperationTest extends TestCase
{
    public function test_stage_c_policy_transition_is_fixed_allowlisted_write_operation(): void
    {
        $service = app(DeploymentConsoleService::class);

        $this->assertSame('APPLY-GROUP-POLICY', $service->confirmationFor('stage_c_group_policy_apply'));
        $this->assertTrue($service->operations()['stage_c_group_policy_apply']['write']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', [
                '--class' => 'StageCCanonicalGroupPolicySeeder',
                '--force' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Stage C group policies applied');

        Log::shouldReceive('channel')->once()->with('deployment-console')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'deployment_console_operation'
                && $context['operation'] === 'stage_c_group_policy_apply'
                && $context['write'] === true
                && $context['success'] === true;
        });

        $result = $service->run('stage_c_group_policy_apply', 1, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertSame('Stage C group policies applied', $result['output']);
    }

    public function test_stage_c_policy_transition_is_allowed_by_the_http_run_route(): void
    {
        $routeSource = file_get_contents(base_path('routes/deployment-console.php'));

        $this->assertStringContainsString("'stage_c_group_policy_apply'", $routeSource);
    }
}
