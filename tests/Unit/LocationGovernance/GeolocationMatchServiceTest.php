<?php

namespace Tests\Unit\LocationGovernance;

use App\Data\Geocoding\ReverseGeocodeResult;
use App\Services\LocationGovernance\GeolocationMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Geocoding\FakeReverseGeocoder;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class GeolocationMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_gps_result_matches_the_deepest_canonical_location_without_mutating_it(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری'],
        );
        $city = $path->last();
        $originalLatitude = $city->centroid_latitude;
        $originalLongitude = $city->centroid_longitude;

        $geocoder = FakeReverseGeocoder::returning(new ReverseGeocodeResult(
            countryCode: 'IR',
            components: [
                'country' => 'ایران',
                'province' => 'مازندران',
                'county' => 'ساری',
                'section' => 'مرکزی',
                'city' => 'ساری',
            ],
            confidence: 0.93,
            provider: 'fake',
        ));

        $match = (new GeolocationMatchService($geocoder))->match(36.5659, 53.0586, 'fa');

        $this->assertSame('matched', $match->status);
        $this->assertSame($city->id, $match->suggestedLocationId);
        $this->assertSame(0.93, $match->confidence);
        $this->assertTrue($match->gpsConsistent);
        $this->assertTrue($match->manualSelectionAvailable);
        $this->assertSame($originalLatitude, $city->fresh()->centroid_latitude);
        $this->assertSame($originalLongitude, $city->fresh()->centroid_longitude);
    }

    public function test_conflicting_gps_never_overrides_manual_selection(): void
    {
        $schema = LocationFixture::iranSchema();
        $gpsCity = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری'],
        )->last();
        $manualCity = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['ایران', 'تهران', 'تهران', 'مرکزی', 'تهران'],
        )->last();

        $geocoder = FakeReverseGeocoder::returning(new ReverseGeocodeResult(
            countryCode: 'IR',
            components: ['city' => 'ساری'],
            confidence: 0.9,
            provider: 'fake',
        ));

        $match = (new GeolocationMatchService($geocoder))->match(
            36.5659,
            53.0586,
            'fa',
            $manualCity,
        );

        $this->assertSame('conflict', $match->status);
        $this->assertSame($gpsCity->id, $match->suggestedLocationId);
        $this->assertSame($manualCity->id, $match->manualLocationId);
        $this->assertFalse($match->gpsConsistent);
        $this->assertTrue($match->manualSelectionAvailable);
    }

    public function test_provider_failure_preserves_manual_flow_and_returns_non_blocking_result(): void
    {
        $geocoder = FakeReverseGeocoder::throwing(new RuntimeException('provider unavailable'));

        $match = (new GeolocationMatchService($geocoder))->match(36.5659, 53.0586, 'fa');

        $this->assertSame('provider_error', $match->status);
        $this->assertNull($match->suggestedLocationId);
        $this->assertFalse($match->gpsConsistent);
        $this->assertTrue($match->manualSelectionAvailable);
    }
}
