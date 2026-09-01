<?php

namespace Tests\Feature;

use App\Support\EarthCoopVite;
use Illuminate\Foundation\Vite;
use Tests\TestCase;

class EarthCoopViteHotFileTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(public_path('hot'));
        parent::tearDown();
    }

    public function test_stale_loopback_hot_file_falls_back_to_manifest_build(): void
    {
        file_put_contents(public_path('hot'), 'http://127.0.0.1:65534');

        $vite = app(Vite::class);

        $this->assertInstanceOf(EarthCoopVite::class, $vite);
        $this->assertFalse($vite->isRunningHot());
        $this->assertFileDoesNotExist(public_path('hot'));
    }

    public function test_stale_loopback_hot_file_is_checked_even_when_app_env_is_not_local(): void
    {
        app()->detectEnvironment(fn () => 'production');
        file_put_contents(public_path('hot'), 'http://127.0.0.1:65534');

        try {
            $vite = new EarthCoopVite();

            $this->assertFalse($vite->isRunningHot());
            $this->assertFileDoesNotExist(public_path('hot'));
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }
}
