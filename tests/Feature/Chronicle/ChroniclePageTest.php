<?php

namespace Tests\Feature\Chronicle;

use Tests\TestCase;

class ChroniclePageTest extends TestCase
{
    public function test_public_chronicle_route_exists(): void
    {
        $response = $this->get('/chronicle');

        $response->assertOk();
        $response->assertSee('گاه‌شمار ارث‌کوپ');
        $response->assertSee('EarthCoop Chronicle');
        $response->assertSee('مبدأ دوران ارث‌کوپ');
        $response->assertSee('۱ فروردین ۱۴۰۱');
        $response->assertSee('March 21, 2022');
    }

    public function test_chronicle_keeps_earthcoop_year_outside_temporal_core(): void
    {
        $source = file_get_contents(app_path('Chronicle/EarthCoopYearCalculator.php'));
        $temporal = file_get_contents(app_path('Temporal/TemporalManager.php'));

        $this->assertStringContainsString('EarthCoopYearCalculator', $source);
        $this->assertStringNotContainsString('EarthCoopYear', $temporal);
        $this->assertStringNotContainsString('EarthCoopEpoch', $temporal);
    }
}
