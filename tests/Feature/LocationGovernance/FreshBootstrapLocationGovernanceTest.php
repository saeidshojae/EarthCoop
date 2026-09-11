<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use App\Models\Location;
use App\Models\LocationSchema;
use App\Models\LocationType;
use App\Models\MembershipDimension;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreshBootstrapLocationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_seeds_only_canonical_schema_types_dimensions_and_policies(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $schema = LocationSchema::query()
            ->where('key', 'ir-reference-v1')
            ->where('country_code', 'IR')
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame('v1', (string) $schema->version);
        $this->assertSame(13, $schema->types()->count());
        $this->assertSame(
            [
                'alley', 'building', 'city', 'complex', 'country', 'county', 'neighborhood',
                'province', 'rural_district', 'section', 'street', 'urban_region', 'village',
            ],
            $schema->types()->pluck('key')->sort()->values()->all()
        );

        $this->assertSame(13, LocationType::query()->count());
        $this->assertSame(5, MembershipDimension::query()->count());
        $this->assertSame(
            ['age', 'gender', 'profession', 'public', 'specialty'],
            MembershipDimension::query()->pluck('key')->sort()->values()->all()
        );

        $this->assertGreaterThan(0, GovernanceCapabilityPolicy::query()->count());
        $this->assertGreaterThan(0, GroupCreationPolicy::query()->count());

        // Reference geography itself must come from the audited importer, not this seed.
        $this->assertSame(0, Location::query()->count());
    }

    public function test_bootstrap_is_idempotent_and_does_not_duplicate_reference_contracts(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $firstCounts = [
            'schemas' => LocationSchema::query()->count(),
            'types' => LocationType::query()->count(),
            'dimensions' => MembershipDimension::query()->count(),
            'capability_policies' => GovernanceCapabilityPolicy::query()->count(),
            'creation_policies' => GroupCreationPolicy::query()->count(),
        ];

        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $this->assertSame($firstCounts, [
            'schemas' => LocationSchema::query()->count(),
            'types' => LocationType::query()->count(),
            'dimensions' => MembershipDimension::query()->count(),
            'capability_policies' => GovernanceCapabilityPolicy::query()->count(),
            'creation_policies' => GroupCreationPolicy::query()->count(),
        ]);
        $this->assertSame(0, Location::query()->count());
    }
}
