<?php

namespace Tests\Unit\Deployment;

use App\Services\Deployment\DeploymentConsoleService;
use InvalidArgumentException;
use Tests\TestCase;

class DeploymentConsoleServiceTest extends TestCase
{
    public function test_operation_catalog_is_exact_and_write_confirmations_are_fixed(): void
    {
        $service = app(DeploymentConsoleService::class);

        $this->assertSame([
            'migration_status',
            'reference_dry_run',
            'topology_dry_run',
            'readiness',
            'flag_status',
            'migrate',
            'bootstrap',
            'reference_apply',
            'topology_apply',
        ], array_keys($service->operations()));

        $this->assertSame('MIGRATE', $service->confirmationFor('migrate'));
        $this->assertSame('BOOTSTRAP', $service->confirmationFor('bootstrap'));
        $this->assertSame('APPLY-IR', $service->confirmationFor('reference_apply'));
        $this->assertSame('APPLY-GOV-IR', $service->confirmationFor('topology_apply'));
        $this->assertNull($service->confirmationFor('migration_status'));
        $this->assertNull($service->confirmationFor('reference_dry_run'));
        $this->assertNull($service->confirmationFor('topology_dry_run'));
        $this->assertNull($service->confirmationFor('readiness'));
        $this->assertNull($service->confirmationFor('flag_status'));
    }

    public function test_console_is_disabled_by_default_and_secret_compare_fails_closed(): void
    {
        config()->set('deployment-console.enabled', false);
        config()->set('deployment-console.secret', null);

        $service = app(DeploymentConsoleService::class);

        $this->assertFalse($service->isEnabled());
        $this->assertFalse($service->secretMatches('anything'));

        config()->set('deployment-console.secret', 'temporary-secret');

        $this->assertTrue($service->secretMatches('temporary-secret'));
        $this->assertFalse($service->secretMatches('wrong-secret'));
        $this->assertFalse($service->secretMatches(''));
    }

    public function test_unknown_operation_is_rejected_before_execution(): void
    {
        $service = app(DeploymentConsoleService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->confirmationFor('arbitrary-command');
    }
}
