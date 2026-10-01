<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DeploymentVendorPackageBoundaryTest extends TestCase
{
    public function test_deploy_workflow_uses_single_vendor_package_not_file_by_file_vendor_sync(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/deploy.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('**/vendor/**', $workflow);
        $this->assertStringContainsString('storage/**', $workflow);
        $this->assertStringContainsString('composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts', $workflow);
        $this->assertStringContainsString('sha256sum composer.lock', $workflow);
        $this->assertStringContainsString('vendor-${LOCK_SHA}.zip', $workflow);
        $this->assertStringContainsString('manifest.json', $workflow);
        $this->assertStringContainsString('local-dir: ./.deployment/vendor-package/', $workflow);
        $this->assertStringContainsString('server-dir: ./storage/deployment/vendor-packages/', $workflow);
        $this->assertStringContainsString('.ftp-deploy-vendor-package-state.json', $workflow);
    }

    public function test_vendor_package_php_surface_has_no_arbitrary_execution_primitive(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            $root.'/app/Services/Deployment/VendorPackageInstaller.php',
            $root.'/app/Console/Commands/InstallVendorPackage.php',
            $root.'/app/Console/Commands/VendorPackageStatus.php',
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression(
                '/(^|[^A-Za-z0-9_])(shell_exec|exec|eval|system|passthru|proc_open)\s*\(/m',
                $source
            );
        }
    }
}
