<?php

namespace Tests\Feature\LocationGovernance;

use App\Services\GroupService;
use Tests\Support\LocationGovernance\LegacySpatialFixture;
use Tests\TestCase;

class LegacySpatialBaselineContractTest extends TestCase
{
    public function test_legacy_urban_sari_path_resolves_city_and_neighborhood(): void
    {
        $user = LegacySpatialFixture::urbanSariUser();

        $levels = app(GroupService::class)->getLocationLevels($user);
        $keys = collect($levels)->pluck('level')->all();

        $this->assertContains('country', $keys);
        $this->assertContains('province', $keys);
        $this->assertContains('county', $keys);
        $this->assertContains('city', $keys);
        $this->assertContains('neighborhood', $keys);
    }

    public function test_legacy_rural_path_can_resolve_village_without_city(): void
    {
        $user = LegacySpatialFixture::ruralUserWithoutCity();

        $levels = app(GroupService::class)->getLocationLevels($user);
        $keys = collect($levels)->pluck('level')->all();

        $this->assertContains('rural', $keys);
        $this->assertContains('village', $keys);
        $this->assertNotContains('city', $keys);
    }
}
