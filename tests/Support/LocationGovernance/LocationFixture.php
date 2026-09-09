<?php

namespace Tests\Support\LocationGovernance;

use App\Models\LocationSchema;
use App\Models\LocationType;
use Illuminate\Support\Collection;

final class LocationFixture
{
    public static function iranSchema(): LocationSchema
    {
        $schema = LocationSchema::factory()->create([
            'key' => 'ir-reference-v1',
            'country_code' => 'IR',
            'name' => 'Iran reference geography',
            'version' => '1',
            'status' => 'active',
        ]);

        $types = collect([
            'country' => ['Country', true, false],
            'province' => ['Province', false, false],
            'county' => ['County', false, false],
            'section' => ['Section', false, false],
            'city' => ['City', false, true],
            'rural_district' => ['Rural district', false, false],
            'village' => ['Village', false, true],
        ])->mapWithKeys(function (array $definition, string $key): array {
            [$name, $isRoot, $isEndpoint] = $definition;

            return [$key => LocationType::factory()->create([
                'key' => $key,
                'canonical_name' => $name,
                'is_residence_endpoint' => $isEndpoint,
            ])];
        });

        foreach ($types as $key => $type) {
            $schema->types()->attach($type->id, [
                'is_root' => $key === 'country',
                'is_residence_endpoint' => (bool) $type->is_residence_endpoint,
            ]);
        }

        foreach ([
            ['country', 'province'],
            ['province', 'county'],
            ['county', 'section'],
            ['section', 'city'],
            ['section', 'rural_district'],
            ['rural_district', 'village'],
        ] as [$parent, $child]) {
            $schema->typeRelations()->create([
                'parent_type_id' => $types[$parent]->id,
                'child_type_id' => $types[$child]->id,
            ]);
        }

        return $schema->fresh(['types', 'typeRelations.parentType', 'typeRelations.childType']);
    }

    public static function allowedChildTypeKeys(LocationSchema $schema, string $parentTypeKey): Collection
    {
        $parentTypeId = $schema->types->firstWhere('key', $parentTypeKey)?->id;

        return $schema->typeRelations
            ->where('parent_type_id', $parentTypeId)
            ->map(fn ($relation) => $relation->childType->key)
            ->values();
    }
}
