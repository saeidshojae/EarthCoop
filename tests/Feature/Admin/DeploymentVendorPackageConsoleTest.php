<?php

namespace Tests\Feature\Admin;

use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeploymentVendorPackageConsoleTest extends TestCase
{
    public function test_vendor_package_status_uses_only_fixed_command_and_no_arguments(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('deployment:vendor-package-status', [])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('{"pending":{"available":false}}');
        $this->expectAudit('vendor_package_status', false, 0, true);

        $result = app(DeploymentConsoleService::class)->run('vendor_package_status', 91, '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('pending', $result['output']);
    }

    public function test_vendor_package_install_uses_only_fixed_confirmation_argument(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('deployment:install-vendor-package', [
                '--confirm' => 'INSTALL-VENDOR-PACKAGE',
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Vendor package installed and verified.');
        $this->expectAudit('vendor_package_install', true, 0, true);

        $result = app(DeploymentConsoleService::class)->run('vendor_package_install', 91, '127.0.0.1');

        $this->assertTrue($result['success']);
    }

    public function test_install_command_has_no_manifest_package_or_path_argument(): void
    {
        $source = file_get_contents(app_path('Console/Commands/InstallVendorPackage.php'));

        $this->assertStringContainsString("deployment:install-vendor-package {--confirm=}", $source);
        $this->assertStringNotContainsString('{manifest', $source);
        $this->assertStringNotContainsString('{package', $source);
        $this->assertStringNotContainsString('{path', $source);
        $this->assertStringNotContainsString('--manifest', $source);
        $this->assertStringNotContainsString('--package', $source);
        $this->assertStringNotContainsString('--path', $source);
    }

    private function expectAudit(string $operation, bool $write, int $exitCode, bool $success): void
    {
        Log::shouldReceive('channel')->once()->with('deployment-console')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use ($operation, $write, $exitCode, $success): bool {
            return $message === 'deployment_console_operation'
                && $context === [
                    'user_id' => 91,
                    'operation' => $operation,
                    'write' => $write,
                    'exit_code' => $exitCode,
                    'success' => $success,
                    'ip' => '127.0.0.1',
                ];
        });
    }
}
