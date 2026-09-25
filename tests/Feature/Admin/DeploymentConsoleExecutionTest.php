<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Deployment\DeploymentConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DeploymentConsoleExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('deployment-console.enabled', true);
        config()->set('deployment-console.secret', 'ultra-secret-value');
    }

    public function test_service_runs_migration_status_with_exact_command_and_arguments(): void
    {
        $this->expectArtisan('migrate:status', [], 0, "Migration table\n");
        $this->expectSanitizedAudit('migration_status', false, 0, true, 41, '127.0.0.1');

        $result = app(DeploymentConsoleService::class)->run('migration_status', 41, '127.0.0.1');

        $this->assertSame('migration_status', $result['operation']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertTrue($result['success']);
        $this->assertSame('Migration table', $result['output']);
    }

    public function test_service_runs_reference_dry_run_with_exact_fixed_arguments(): void
    {
        $this->expectArtisan('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--dry-run' => true,
        ], 0, 'create=10 update=0 deactivate=0 conflict=0 unchanged=2');
        $this->expectSanitizedAudit('reference_dry_run', false, 0, true, 42, null);

        $result = app(DeploymentConsoleService::class)->run('reference_dry_run', 42);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('conflict=0', $result['output']);
    }

    public function test_service_runs_topology_dry_run_with_exact_fixed_arguments(): void
    {
        $this->expectArtisan('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--dry-run' => true,
        ], 0, 'create: 5 update: 0 conflict: 0 unchanged: 0');
        $this->expectSanitizedAudit('topology_dry_run', false, 0, true, 49, null);

        $result = app(DeploymentConsoleService::class)->run('topology_dry_run', 49);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('conflict: 0', $result['output']);
    }

    public function test_service_runs_readiness_with_exact_command(): void
    {
        $this->expectArtisan('location-governance:readiness', [], 0, 'READY');
        $this->expectSanitizedAudit('readiness', false, 0, true, 43, null);

        $result = app(DeploymentConsoleService::class)->run('readiness', 43);

        $this->assertSame('READY', $result['output']);
    }

    public function test_service_runs_migrate_with_force_only(): void
    {
        $this->expectArtisan('migrate', ['--force' => true], 0, 'Migrated');
        $this->expectSanitizedAudit('migrate', true, 0, true, 44, '10.0.0.4');

        $result = app(DeploymentConsoleService::class)->run('migrate', 44, '10.0.0.4');

        $this->assertTrue($result['success']);
    }

    public function test_service_runs_bootstrap_with_exact_seeder_and_force(): void
    {
        $this->expectArtisan('db:seed', [
            '--class' => 'LocationGovernanceBootstrapSeeder',
            '--force' => true,
        ], 0, 'Seeded');
        $this->expectSanitizedAudit('bootstrap', true, 0, true, 45, null);

        $result = app(DeploymentConsoleService::class)->run('bootstrap', 45);

        $this->assertTrue($result['success']);
    }

    public function test_service_runs_reference_apply_with_exact_fixed_arguments(): void
    {
        $this->expectArtisan('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ], 0, 'Applied');
        $this->expectSanitizedAudit('reference_apply', true, 0, true, 46, null);

        $result = app(DeploymentConsoleService::class)->run('reference_apply', 46);

        $this->assertTrue($result['success']);
    }

    public function test_service_runs_topology_apply_with_exact_fixed_arguments(): void
    {
        $this->expectArtisan('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ], 0, 'Applied topology');
        $this->expectSanitizedAudit('topology_apply', true, 0, true, 50, null);

        $result = app(DeploymentConsoleService::class)->run('topology_apply', 50);

        $this->assertTrue($result['success']);
    }

    public function test_iran_1404_cutover_operations_use_only_fixed_commands_tokens_and_arguments(): void
    {
        $cases = [
            ['iran_v1_v2_audit', 'location:iran-v1-v2-runtime-audit', [], false, 'audit'],
            ['reference_v2_dry_run', 'location:reference-import', [
                'country' => 'IR', '--dataset-version' => 'v2', '--dry-run' => true,
            ], false, 'reference dry'],
            ['settlement_v2_dry_run', 'location:iran-1404-settlement-catalog', [
                '--dry-run' => true,
            ], false, 'settlement dry'],
            ['topology_v2_dry_run', 'location-governance:reference-topology', [
                'country' => 'IR', '--dataset-version' => 'v2', '--dry-run' => true,
            ], false, 'topology dry'],
            ['reference_v2_apply', 'location:reference-import', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-IR-1404-V2-PRODUCTION',
            ], true, 'reference applied'],
            ['settlement_v2_apply', 'location:iran-1404-settlement-catalog', [
                '--apply' => true, '--confirm' => 'APPLY-IR-SETTLEMENT-CATALOG-PRODUCTION',
            ], true, 'settlements applied'],
            ['topology_v2_apply', 'location-governance:reference-topology', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-GOV-IR-1404-V2-PRODUCTION',
            ], true, 'topology applied'],
        ];

        foreach ($cases as $index => [$operation, $command, $arguments, $write, $output]) {
            $userId = 60 + $index;
            $this->expectArtisan($command, $arguments, 0, $output);
            $this->expectSanitizedAudit($operation, $write, 0, true, $userId, null);

            $result = app(DeploymentConsoleService::class)->run($operation, $userId);

            $this->assertTrue($result['success'], $operation);
            $this->assertSame($output, $result['output'], $operation);
        }
    }

    public function test_flag_status_never_invokes_artisan_and_returns_rollout_and_iran_cutover_flags(): void
    {
        config()->set('location-governance.runtime_enabled', true);
        config()->set('location-governance.registration_enabled', false);
        config()->set('location-governance.groups_enabled', true);
        config()->set('location-governance.elections_enabled', false);
        config()->set('location-governance.projects_enabled', false);
        config()->set('iran_settlement_catalog.v2_runtime_enabled', false);
        config()->set('iran_settlement_catalog.enabled', false);
        config()->set('iran_settlement_catalog.claims_enabled', false);

        Artisan::shouldReceive('call')->never();
        Artisan::shouldReceive('output')->never();
        $this->expectSanitizedAudit('flag_status', false, 0, true, 47, null);

        $service = app(DeploymentConsoleService::class);
        $this->assertSame([
            'runtime_enabled' => true,
            'registration_enabled' => false,
            'groups_enabled' => true,
            'elections_enabled' => false,
            'projects_enabled' => false,
            'iran_v2_runtime_enabled' => false,
            'iran_settlement_catalog_enabled' => false,
            'iran_settlement_claims_enabled' => false,
        ], $service->flags());

        $result = $service->run('flag_status', 47);

        $this->assertSame(0, $result['exit_code']);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('runtime_enabled=true', $result['output']);
        $this->assertStringContainsString('projects_enabled=false', $result['output']);
        $this->assertStringContainsString('iran_v2_runtime_enabled=false', $result['output']);
        $this->assertStringContainsString('iran_settlement_catalog_enabled=false', $result['output']);
        $this->assertStringContainsString('iran_settlement_claims_enabled=false', $result['output']);
    }

    public function test_non_zero_exit_code_is_returned_as_failure_without_automatic_recovery(): void
    {
        $this->expectArtisan('location-governance:readiness', [], 1, 'NOT READY');
        $this->expectSanitizedAudit('readiness', false, 1, false, 48, null);

        $result = app(DeploymentConsoleService::class)->run('readiness', 48);

        $this->assertSame(1, $result['exit_code']);
        $this->assertFalse($result['success']);
        $this->assertSame('NOT READY', $result['output']);
    }

    public function test_successful_http_execution_never_flashes_or_renders_deployment_secret(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->expectArtisan('migrate:status', [], 0, 'Status output');
        $this->expectSanitizedAudit('migration_status', false, 0, true, $user->id, '127.0.0.1');

        $response = $this->actingAs($user)
            ->from('/admin/deployment-console')
            ->post('/admin/deployment-console/run/migration_status', [
                'deployment_secret' => 'ultra-secret-value',
            ]);

        $response->assertRedirect('/admin/deployment-console');
        $response->assertSessionHas('deployment_console_result');
        $response->assertSessionMissing('_old_input.deployment_secret');
        $response->assertDontSee('ultra-secret-value');
        $this->assertSame('', old('deployment_secret', ''));
    }

    private function expectArtisan(string $command, array $arguments, int $exitCode, string $output): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with($command, $arguments)
            ->andReturn($exitCode);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn($output);
    }

    private function expectSanitizedAudit(
        string $operation,
        bool $write,
        int $exitCode,
        bool $success,
        int $userId,
        ?string $ip
    ): void {
        Log::shouldReceive('channel')
            ->once()
            ->with('deployment-console')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($operation, $write, $exitCode, $success, $userId, $ip): bool {
                $this->assertSame('deployment_console_operation', $message);
                $this->assertSame([
                    'user_id' => $userId,
                    'operation' => $operation,
                    'write' => $write,
                    'exit_code' => $exitCode,
                    'success' => $success,
                    'ip' => $ip,
                ], $context);

                $serialized = json_encode($context);
                $this->assertStringNotContainsString('ultra-secret-value', $serialized);
                $this->assertArrayNotHasKey('secret', $context);
                $this->assertArrayNotHasKey('deployment_secret', $context);
                $this->assertArrayNotHasKey('_token', $context);

                return true;
            });
    }
}
