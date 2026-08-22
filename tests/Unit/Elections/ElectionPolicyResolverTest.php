<?php

namespace Tests\Unit\Elections;

use App\Models\Group;
use App\Models\GroupSetting;
use App\Services\Elections\ElectionPolicyResolver;
use PHPUnit\Framework\TestCase;

class ElectionPolicyResolverTest extends TestCase
{
    private ElectionPolicyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ElectionPolicyResolver();
    }

    public function test_base_location_level_is_used_for_general_groups(): void
    {
        $group = new Group(['location_level' => 'neighborhood']);
        $this->assertSame('neighborhood', $this->resolver->levelKeyForGroup($group));
    }

    public function test_legacy_section_level_is_normalized_to_district_at_policy_boundary(): void
    {
        $this->assertSame('district', $this->resolver->levelKeyForGroup(new Group([
            'location_level' => 'section',
        ])));

        $this->assertSame('district_job', $this->resolver->levelKeyForGroup(new Group([
            'location_level' => 'section',
            'specialty_id' => 11,
        ])));
    }

    public function test_specialized_group_suffixes_follow_legacy_contract(): void
    {
        $this->assertSame(
            'neighborhood_job',
            $this->resolver->levelKeyForGroup(new Group([
                'location_level' => 'neighborhood',
                'specialty_id' => 10,
            ])),
        );

        $this->assertSame(
            'city_experience',
            $this->resolver->levelKeyForGroup(new Group([
                'location_level' => 'city',
                'experience_id' => 20,
            ])),
        );

        $this->assertSame(
            'province_age',
            $this->resolver->levelKeyForGroup(new Group([
                'location_level' => 'province',
                'age_group_id' => 30,
            ])),
        );

        $this->assertSame(
            'country_gender',
            $this->resolver->levelKeyForGroup(new Group([
                'location_level' => 'country',
                'gender' => 'female',
            ])),
        );
    }

    public function test_specialty_has_highest_legacy_precedence_when_multiple_dimensions_exist(): void
    {
        $group = new Group([
            'location_level' => 'city',
            'specialty_id' => 1,
            'experience_id' => 2,
            'age_group_id' => 3,
            'gender' => 'male',
        ]);

        $this->assertSame('city_job', $this->resolver->levelKeyForGroup($group));
    }

    public function test_seat_counts_use_canonical_schema_names_and_are_never_negative(): void
    {
        $setting = new GroupSetting();
        $setting->manager_count = 7;
        $setting->inspector_count = 3;

        $this->assertSame(7, $this->resolver->managerSeatCount($setting));
        $this->assertSame(3, $this->resolver->inspectorSeatCount($setting));

        $setting->manager_count = -1;
        $setting->inspector_count = -2;

        $this->assertSame(0, $this->resolver->managerSeatCount($setting));
        $this->assertSame(0, $this->resolver->inspectorSeatCount($setting));
    }
}
