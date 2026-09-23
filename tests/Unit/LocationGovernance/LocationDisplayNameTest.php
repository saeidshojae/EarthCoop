<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Support\LocationDisplayName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_display_name_prefixes_all_canonical_levels_without_duplicating_existing_prefix(): void
    {
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country','province','county','section','city','urban_region','neighborhood','street','alley','complex','building'],
            ['ایران','مازندران','ساری','مرکزی','ساری','منطقه ۱','محله مرجع','الف','دوستی','بهارستان','35']
        );

        $expected = [
            'کشور ایران',
            'استان مازندران',
            'شهرستان ساری',
            'بخش مرکزی',
            'شهر ساری',
            'منطقه ۱',
            'محله مرجع',
            'خیابان الف',
            'کوچه دوستی',
            'مجتمع بهارستان',
            'ساختمان 35',
        ];

        $actual = $path->values()->map(fn (Location $location) => LocationDisplayName::typed($location, 'fa'))->all();

        $this->assertSame($expected, $actual);
    }

    public function test_typed_display_name_does_not_duplicate_type_prefix_or_aliases(): void
    {
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $province = LocationFixture::createPath($schema, ['country','province'], ['ایران','استان مازندران'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'], ['ایران','مازندران','ساری','مرکزی','ساری','منطقه شهری ۶'])->last();

        $this->assertSame('استان مازندران', LocationDisplayName::typed($province, 'fa'));
        $this->assertSame('منطقه ۶', LocationDisplayName::typed($region, 'fa'));
    }

    public function test_typed_display_name_includes_continent_prefix_in_global_path(): void
    {
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $continentType = $schema->types()->where('key', 'continent')->first();

        if ($continentType === null) {
            $continentType = \App\Models\LocationType::query()->firstOrCreate(
                ['key' => 'continent'],
                ['canonical_name' => 'Continent']
            );
        }

        $continent = Location::query()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $continentType->id,
            'canonical_name' => 'آسیا',
            'country_code' => null,
            'status' => 'active',
        ]);

        $this->assertSame('قاره آسیا', LocationDisplayName::typed($continent, 'fa'));
    }

    public function test_typed_persian_village_name_does_not_repeat_roosta_prefix(): void
    {
        app()->setLocale('fa');
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village',
        ])->last();
        $village->forceFill([
            'canonical_name' => 'Reference Village Without Neighborhood',
            'localized_names' => ['fa' => 'روستای مرجع بدون محله'],
        ]);

        $this->assertSame('روستای مرجع بدون محله', LocationDisplayName::typed($village));
        $this->assertSame('روستای مرجع بدون محله', LocationDisplayName::for($village, 'fa-IR'));
    }

}
