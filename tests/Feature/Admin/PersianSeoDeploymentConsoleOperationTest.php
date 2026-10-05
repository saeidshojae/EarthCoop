<?php

namespace Tests\Feature\Admin;

use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PersianSeoDeploymentConsoleOperationTest extends TestCase
{
    public function test_persian_seo_blog_publish_is_fixed_allowlisted_write_operation(): void
    {
        $service = app(DeploymentConsoleService::class);

        $this->assertSame('PUBLISH-PERSIAN-SEO-BLOG', $service->confirmationFor('persian_seo_blog_publish'));
        $this->assertTrue($service->operations()['persian_seo_blog_publish']['write']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', [
                '--class' => 'PersianSeoBlogSeeder',
                '--force' => true,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Persian SEO blog wave published');

        Log::shouldReceive('channel')->once()->with('deployment-console')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'deployment_console_operation'
                && $context['operation'] === 'persian_seo_blog_publish'
                && $context['write'] === true
                && $context['success'] === true;
        });

        $result = $service->run('persian_seo_blog_publish', 1, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertSame('Persian SEO blog wave published', $result['output']);
    }

    public function test_persian_seo_blog_publish_is_allowed_by_the_http_run_route(): void
    {
        $routeSource = file_get_contents(base_path('routes/deployment-console.php'));

        $this->assertStringContainsString("'persian_seo_blog_publish'", $routeSource);
    }
}
