<?php

namespace Tests\Unit\Membership;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MembershipEngineTest extends TestCase
{
    public function test_engine_exposes_the_single_deterministic_resolution_entrypoint(): void
    {
        $class = 'App\\Services\\Membership\\MembershipEngine';
        $this->assertTrue(class_exists($class), 'MembershipEngine must exist.');

        $method = (new ReflectionClass($class))->getMethod('resolve');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('user', $parameters[0]->getName());
        $this->assertSame(User::class, $parameters[0]->getType()?->getName());
        $this->assertSame('materialize', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertFalse($parameters[1]->getDefaultValue());
        $this->assertSame('App\\Data\\Membership\\MembershipResolution', $method->getReturnType()?->getName());
    }

    public function test_resolution_contract_carries_all_required_outputs(): void
    {
        foreach ([
            'App\\Data\\Membership\\MembershipIntent',
            'App\\Data\\Membership\\MembershipResolution',
            'App\\Services\\Membership\\MembershipAuditService',
        ] as $class) {
            $this->assertTrue(class_exists($class), "Missing {$class}.");
        }

        $reflection = new ReflectionClass('App\\Data\\Membership\\MembershipResolution');
        $properties = collect($reflection->getProperties())->map->getName()->all();

        foreach (['officialGovernanceAreas', 'communityAreas', 'materializableIntents', 'suppressedIntents', 'auditFingerprint'] as $property) {
            $this->assertContains($property, $properties, "MembershipResolution must expose {$property}.");
        }
    }

    public function test_resolution_audit_schema_is_additive_and_fingerprint_oriented(): void
    {
        $path = base_path('database/migrations/2026_09_10_000006_create_membership_resolution_audits.php');
        $this->assertFileExists($path);

        $source = file_get_contents($path);
        $this->assertStringContainsString('membership_resolution_audits', $source);
        $this->assertStringContainsString('user_id', $source);
        $this->assertStringContainsString('fingerprint', $source);
        $this->assertStringContainsString('resolution', $source);
    }
}
