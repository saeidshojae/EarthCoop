<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class DeploymentConsoleSourceContractTest extends TestCase
{
    public function test_deployment_console_has_no_forbidden_execution_surface(): void
    {
        $paths = [
            app_path('Services/Deployment/DeploymentConsoleService.php'),
            app_path('Http/Controllers/Admin/DeploymentConsoleController.php'),
            base_path('routes/deployment-console.php'),
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);

            $source = file_get_contents($path);

            $this->assertStringNotContainsString('migrate:fresh', $source);
            $this->assertStringNotContainsString('migrate:reset', $source);
            $this->assertStringNotContainsString('migrate:rollback', $source);
            $this->assertStringNotContainsString('shell_exec', $source);
            $this->assertStringNotContainsString('exec(', $source);
            $this->assertStringNotContainsString('eval(', $source);
            $this->assertStringNotContainsString('DB::statement', $source);
            $this->assertStringNotContainsString('Artisan::call($request', $source);
            $this->assertStringNotContainsString('Artisan::call($operation', $source);
        }
    }

    public function test_cpanel_checklist_documents_browser_console_and_keeps_destructive_commands_forbidden(): void
    {
        $path = base_path('docs/location-governance/CPANEL_FRESH_CANONICAL_START_CHECKLIST.md');
        $this->assertFileExists($path);

        $source = file_get_contents($path);

        foreach ([
            'DEPLOYMENT_CONSOLE_ENABLED',
            'DEPLOYMENT_CONSOLE_SECRET',
            '/admin/deployment-console',
            'phpMyAdmin',
            'migration status',
            'reference_dry_run',
            'topology_dry_run',
            'topology_apply',
            'APPLY-GOV-IR',
            'readiness',
            'HARD STOP',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        foreach (['migrate:fresh', 'migrate:reset', 'migrate:rollback', 'truncate', 'drop'] as $forbiddenOperation) {
            $this->assertStringContainsString($forbiddenOperation, strtolower($source));
        }
    }
}
