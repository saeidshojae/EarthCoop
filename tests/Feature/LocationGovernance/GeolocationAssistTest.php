<?php

namespace Tests\Feature\LocationGovernance;

use App\Contracts\Geocoding\ReverseGeocoder;
use App\Data\Geocoding\ReverseGeocodeResult;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Geocoding\FakeReverseGeocoder;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class GeolocationAssistTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_request_an_assistive_match_without_raw_coordinates_being_persisted_or_echoed(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری'],
        )->last();
        $beforeCount = Location::query()->count();
        $beforeLat = $city->centroid_latitude;
        $beforeLng = $city->centroid_longitude;

        $this->app->instance(ReverseGeocoder::class, FakeReverseGeocoder::returning(
            new ReverseGeocodeResult(
                countryCode: 'IR',
                components: ['city' => 'ساری'],
                confidence: 0.94,
                provider: 'fake',
            )
        ));

        $response = $this->actingAs(User::factory()->create())->postJson(
            '/location-governance/geolocation/match',
            [
                'latitude' => 36.565912345,
                'longitude' => 53.058612345,
                'locale' => 'fa',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('suggested_location_id', $city->id)
            ->assertJsonPath('gps_consistent', true)
            ->assertJsonPath('manual_selection_available', true)
            ->assertJsonMissingPath('latitude')
            ->assertJsonMissingPath('longitude');

        $this->assertSame($beforeCount, Location::query()->count());
        $this->assertSame($beforeLat, $city->fresh()->centroid_latitude);
        $this->assertSame($beforeLng, $city->fresh()->centroid_longitude);
    }

    public function test_manual_selection_conflict_is_reported_without_overwriting_or_blocking_the_manual_location(): void
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

        $this->app->instance(ReverseGeocoder::class, FakeReverseGeocoder::returning(
            new ReverseGeocodeResult('IR', ['city' => 'ساری'], 0.91, 'fake')
        ));

        $response = $this->actingAs(User::factory()->create())->postJson(
            '/location-governance/geolocation/match',
            [
                'latitude' => 36.5659,
                'longitude' => 53.0586,
                'locale' => 'fa',
                'manual_location_id' => $manualCity->id,
            ],
        );

        $response->assertOk()
            ->assertJsonPath('status', 'conflict')
            ->assertJsonPath('suggested_location_id', $gpsCity->id)
            ->assertJsonPath('manual_location_id', $manualCity->id)
            ->assertJsonPath('gps_consistent', false)
            ->assertJsonPath('manual_selection_available', true);
    }

    public function test_provider_failure_returns_a_non_blocking_manual_flow(): void
    {
        $this->app->instance(
            ReverseGeocoder::class,
            FakeReverseGeocoder::throwing(new RuntimeException('reverse geocoder unavailable')),
        );

        $response = $this->actingAs(User::factory()->create())->postJson(
            '/location-governance/geolocation/match',
            ['latitude' => 36.5659, 'longitude' => 53.0586, 'locale' => 'fa'],
        );

        $response->assertOk()
            ->assertJsonPath('status', 'provider_error')
            ->assertJsonPath('suggested_location_id', null)
            ->assertJsonPath('manual_selection_available', true);
    }

    public function test_coordinates_are_validated_and_endpoint_requires_authentication(): void
    {
        $this->postJson('/location-governance/geolocation/match', [
            'latitude' => 36.56,
            'longitude' => 53.05,
        ])->assertUnauthorized();

        $this->app->instance(ReverseGeocoder::class, FakeReverseGeocoder::returning(
            new ReverseGeocodeResult('IR', [], null, 'fake')
        ));

        $this->actingAs(User::factory()->create())->postJson(
            '/location-governance/geolocation/match',
            ['latitude' => 91, 'longitude' => -181, 'locale' => 'fa'],
        )->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
