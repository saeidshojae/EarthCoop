<?php

namespace Tests\Feature\Elections;

use App\Models\Address;
use App\Models\Group;
use App\Models\User;
use App\Services\Elections\ElectionGroupHierarchyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectionGroupHierarchyResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_street_is_compressed_into_neighborhood_but_second_street_restores_independent_election_layer(): void
    {
        [$user, $ids] = $this->urbanFixture(includeRegion: true, directNeighborhoodUnderCity: false);

        DB::table('streets')->insert([
            'id' => 9201, 'name' => 'Street A', 'parent_id' => $ids['neighborhood'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Address::where('user_id', $user->id)->update(['street_id' => 9201]);

        // Keep the parent region genuinely multi-constituency so this test
        // isolates only the street -> neighborhood compression rule.
        DB::table('neighborhoods')->insert([
            'id' => 9203, 'name' => 'Neighborhood B', 'parent_id' => $ids['region'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $neighborhood = $this->group('neighborhood', $ids['neighborhood']);
        $street = $this->group('street', 9201);
        $this->group('region', $ids['region']);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertTrue($resolver->isSoleStructuralConstituency($street, $neighborhood));
        $this->assertSame([$neighborhood->id], collect($resolver->compressionChain($street, $user))->pluck('id')->all());

        DB::table('streets')->insert([
            'id' => 9202, 'name' => 'Street B', 'parent_id' => $ids['neighborhood'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($resolver->isSoleStructuralConstituency($street, $neighborhood));
        $this->assertSame([], $resolver->compressionChain($street, $user));
    }

    public function test_single_region_is_compressed_into_city_but_multiple_regions_require_city_election(): void
    {
        [$user, $ids] = $this->urbanFixture(includeRegion: true, directNeighborhoodUnderCity: false);
        $region = $this->group('region', $ids['region']);
        $city = $this->group('city', $ids['city']);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertTrue($resolver->isSoleStructuralConstituency($region, $city));

        DB::table('regions')->insert([
            'id' => 9302, 'name' => 'Region B', 'parent_id' => $ids['city'],
            'province_id' => $ids['province'], 'district_id' => $ids['section'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($resolver->isSoleStructuralConstituency($region, $city));
    }

    public function test_city_without_region_can_treat_direct_neighborhood_as_effective_constituency(): void
    {
        [$user, $ids] = $this->urbanFixture(includeRegion: false, directNeighborhoodUnderCity: true);
        $neighborhood = $this->group('neighborhood', $ids['neighborhood']);
        $city = $this->group('city', $ids['city']);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertSame($city->id, $resolver->higherGroup($neighborhood, $user)?->id);
        $this->assertTrue($resolver->isSoleStructuralConstituency($neighborhood, $city));

        DB::table('neighborhoods')->insert([
            'id' => 9402, 'name' => 'Neighborhood B', 'parent_id' => $ids['city'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($resolver->isSoleStructuralConstituency($neighborhood, $city));
    }

    public function test_single_section_is_compressed_into_county(): void
    {
        [$user, $ids] = $this->urbanFixture(includeRegion: true, directNeighborhoodUnderCity: false);
        $section = $this->group('section', $ids['section']);
        $county = $this->group('county', $ids['county']);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertTrue($resolver->isSoleStructuralConstituency($section, $county));

        DB::table('districts')->insert([
            'id' => 9502, 'name' => 'Section B', 'province_id' => $ids['province'],
            'county_id' => $ids['county'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($resolver->isSoleStructuralConstituency($section, $county));
    }

    public function test_city_and_rural_are_counted_together_as_independent_section_constituencies(): void
    {
        [$user, $ids] = $this->urbanFixture(includeRegion: true, directNeighborhoodUnderCity: false);
        $city = $this->group('city', $ids['city']);
        $section = $this->group('section', $ids['section']);
        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertTrue($resolver->isSoleStructuralConstituency($city, $section));

        DB::table('rurals')->insert([
            'id' => 9601, 'name' => 'Rural A', 'province_id' => $ids['province'],
            'county_id' => $ids['county'], 'district_id' => $ids['section'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, $resolver->structuralConstituencyCount($section, 'city'));
        $this->assertFalse($resolver->isSoleStructuralConstituency($city, $section));
    }

    private function urbanFixture(bool $includeRegion, bool $directNeighborhoodUnderCity): array
    {
        $ids = [
            'continent' => 9001,
            'country' => 9002,
            'province' => 9003,
            'county' => 9004,
            'section' => 9005,
            'city' => 9006,
            'region' => 9007,
            'neighborhood' => 9008,
        ];

        DB::table('continents')->insert([
            'id' => $ids['continent'], 'name' => 'Continent A', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('countries')->insert([
            'id' => $ids['country'], 'name' => 'Country A', 'continent_id' => $ids['continent'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('provinces')->insert([
            'id' => $ids['province'], 'name' => 'Province A', 'country_id' => $ids['country'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('counties')->insert([
            'id' => $ids['county'], 'name' => 'County A', 'province_id' => $ids['province'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('districts')->insert([
            'id' => $ids['section'], 'name' => 'Section A', 'province_id' => $ids['province'],
            'county_id' => $ids['county'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cities')->insert([
            'id' => $ids['city'], 'name' => 'City A', 'province_id' => $ids['province'],
            'county_id' => $ids['county'], 'district_id' => $ids['section'], 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($includeRegion) {
            DB::table('regions')->insert([
                'id' => $ids['region'], 'name' => 'Region A', 'parent_id' => $ids['city'],
                'province_id' => $ids['province'], 'district_id' => $ids['section'], 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('neighborhoods')->insert([
            'id' => $ids['neighborhood'], 'name' => 'Neighborhood A',
            'parent_id' => $directNeighborhoodUnderCity ? $ids['city'] : $ids['region'],
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        Address::create([
            'user_id' => $user->id,
            'continent_id' => $ids['continent'],
            'country_id' => $ids['country'],
            'province_id' => $ids['province'],
            'county_id' => $ids['county'],
            'section_id' => $ids['section'],
            'city_id' => $ids['city'],
            'region_id' => $includeRegion ? $ids['region'] : null,
            'neighborhood_id' => $ids['neighborhood'],
            'status' => 1,
        ]);

        return [$user, $ids];
    }

    private function group(string $level, ?int $addressId): Group
    {
        return Group::firstOrCreate([
            'group_type' => '0',
            'location_level' => $level,
            'address_id' => $addressId,
            'specialty_id' => null,
            'experience_id' => null,
            'age_group_id' => null,
            'gender' => null,
        ], ['name' => 'Election '.$level.' '.($addressId ?? 'global')]);
    }
}
