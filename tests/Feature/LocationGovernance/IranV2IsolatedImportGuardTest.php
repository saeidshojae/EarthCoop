<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class IranV2IsolatedImportGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_apply_requires_exact_confirmation_and_cannot_touch_existing_iran_locations(): void
    {
        $before = Location::query()->count();
        $arguments = [
            'country' => 'IR',
            '--dataset-version' => 'v2',
            '--apply' => true,
        ];

        $this->assertSame(1, Artisan::call('location:reference-import', $arguments));
        $this->assertSame($before, Location::query()->count());

        $this->assertSame(1, Artisan::call('location:reference-import', [
            ...$arguments, '--confirm' => 'WRONG',
        ]));
        $this->assertSame($before, Location::query()->count());

        // An actual existing v1 residence tree must block v2 even if the
        // operator supplies the exact confirmation in a test database.
        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR', '--dataset-version' => 'v1', '--apply' => true,
        ]));
        $existing = Location::query()->count();
        $this->assertGreaterThan(0, $existing);
        $this->assertSame(1, Artisan::call('location:reference-import', [
            ...$arguments, '--confirm' => 'APPLY-IR-1404-V2-ISOLATED',
        ]));
        $this->assertStringContainsString('legacy/non-v2 geography', Artisan::output());
        $this->assertSame($existing, Location::query()->count());
    }

    public function test_v2_apply_is_forbidden_in_production_even_with_confirmation(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->assertSame(1, Artisan::call('location:reference-import', [
                'country' => 'IR',
                '--dataset-version' => 'v2',
                '--apply' => true,
                '--confirm' => 'APPLY-IR-1404-V2-ISOLATED',
            ]));
            $this->assertSame(0, Location::query()->count());
        } finally {
            $this->app['env'] = $original;
        }
    }
}
