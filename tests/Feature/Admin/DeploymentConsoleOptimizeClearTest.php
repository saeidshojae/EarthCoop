<?php

namespace Tests\Feature\Admin;

use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeploymentConsoleOptimizeClearTest extends TestCase
{
    public function test_optimize_clear_is_allowlisted_as_fixed_confirmed_operation(): void
    {
        $service = app(DeploymentConsoleService::class);

        $this->assertArrayHasKey('optimize_clear', $service->operations());
        $this->assertSame('OPTIMIZE-CLEAR', $service->confirmationFor('optimize_clear'));

        Artisan::shouldReceive('call')
            ->once()
            ->with('optimize:clear', [])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Caches cleared');

        Log::shouldReceive('channel')
            ->once()
            ->with('deployment-console')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'deployment_console_operation'
                    && $context['operation'] === 'optimize_clear'
                    && $context['write'] === true
                    && $context['exit_code'] === 0
                    && $context['success'] === true;
            });

        $result = $service->run('optimize_clear', 1, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertSame('Caches cleared', $result['output']);
    }

    public function test_optimize_clear_is_allowed_by_the_http_run_route(): void
    {
        $routeSource = file_get_contents(base_path('routes/deployment-console.php'));

        $this->assertStringContainsString("'optimize_clear'", $routeSource);
    }
}
