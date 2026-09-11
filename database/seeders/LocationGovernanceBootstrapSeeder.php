<?php

namespace Database\Seeders;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use App\Models\LocationSchema;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;
use App\Models\MembershipDimension;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationGovernanceBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $schema = LocationSchema::query()->updateOrCreate(
                ['key' => 'ir-reference-v1'],
                [
                    'country_code' => 'IR',
                    'name' => 'Iran reference geography',
                    'version' => '1',
                    'status' => 'active',
                    'metadata' => ['bootstrap' => true],
                ],
            );

            $typeDefinitions = [
                'country' => ['Country', true, false],
                'province' => ['Province', false, false],
                'county' => ['County', false, false],
                'section' => ['Section', false, false],
                'city' => ['City', false, true],
                'rural_district' => ['Rural district', false, false],
                'village' => ['Village', false, true],
                'urban_region' => ['Urban region', false, false],
                'neighborhood' => ['Neighborhood', false, true],
                'street' => ['Street', false, true],
                'alley' => ['Alley', false, true],
                'complex' => ['Residential complex', false, true],
                'building' => ['Building', false, true],
            ];

            $types = [];
            $sortOrder = 0;
            foreach ($typeDefinitions as $key => $definition) {
                [$name, $isRoot, $isEndpoint] = $definition;

                $type = LocationType::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'canonical_name' => $name,
                        'is_residence_endpoint' => $isEndpoint,
                        'metadata' => ['bootstrap' => true],
                    ],
                );

                $types[$key] = $type;

                $schema->types()->syncWithoutDetaching([
                    $type->id => [
                        'is_root' => $isRoot,
                        'is_residence_endpoint' => $isEndpoint,
                        'sort_order' => $sortOrder,
                        'metadata' => json_encode(['bootstrap' => true]),
                    ],
                ]);
                $schema->types()->updateExistingPivot($type->id, [
                    'is_root' => $isRoot,
                    'is_residence_endpoint' => $isEndpoint,
                    'sort_order' => $sortOrder,
                    'metadata' => json_encode(['bootstrap' => true]),
                ]);

                $sortOrder++;
            }

            foreach ([
                ['country', 'province'],
                ['province', 'county'],
                ['county', 'section'],
                ['section', 'city'],
                ['section', 'rural_district'],
                ['rural_district', 'village'],
                ['city', 'urban_region'],
                ['urban_region', 'neighborhood'],
                ['village', 'neighborhood'],
                ['neighborhood', 'street'],
                ['street', 'alley'],
                ['street', 'complex'],
                ['alley', 'complex'],
                ['complex', 'building'],
            ] as [$parentKey, $childKey]) {
                LocationTypeRelation::query()->updateOrCreate(
                    [
                        'location_schema_id' => $schema->id,
                        'parent_type_id' => $types[$parentKey]->id,
                        'child_type_id' => $types[$childKey]->id,
                    ],
                    ['metadata' => ['bootstrap' => true]],
                );
            }

            $dimensionDefinitions = [
                'public' => 'Public',
                'profession' => 'Profession',
                'specialty' => 'Specialty',
                'age' => 'Age',
                'gender' => 'Gender',
            ];

            foreach ($dimensionDefinitions as $key => $name) {
                $dimension = MembershipDimension::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $name,
                        'resolver_class' => 'App\\Services\\Membership\\'.ucfirst($key).'DimensionResolver',
                        'enabled' => true,
                        'metadata' => ['bootstrap' => true],
                    ],
                );

                // Bootstrap remains intentionally conservative: policy exists, but
                // no membership dimension causes group explosion on a fresh system.
                GroupCreationPolicy::query()->updateOrCreate(
                    [
                        'membership_dimension_id' => $dimension->id,
                        'governance_area_id' => null,
                        'governance_type' => null,
                        'governance_rank' => null,
                        'priority' => 0,
                    ],
                    [
                        'mode' => GroupCreationMode::OnDemand,
                        'threshold' => null,
                        'enabled' => true,
                        'metadata' => ['bootstrap' => true],
                    ],
                );
            }

            GovernanceCapabilityPolicy::query()->updateOrCreate(
                [
                    'scope' => 'default',
                    'country_code' => null,
                    'governance_type' => null,
                ],
                [
                    'capabilities' => [
                        'public_assembly' => true,
                        'chat' => true,
                        'secretariat' => false,
                        'polls' => true,
                        'projects' => true,
                        'internal_elections' => true,
                        'systemic_elections' => false,
                        'managers_inspectors' => false,
                        'delegation' => false,
                        'official_upstream_participation' => false,
                        'group_creation_mode' => 'on_demand',
                    ],
                ],
            );
        });
    }
}
