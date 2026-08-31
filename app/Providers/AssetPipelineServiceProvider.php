<?php

namespace App\Providers;

use App\Support\EarthCoopVite;
use Illuminate\Foundation\Vite;
use Illuminate\Support\ServiceProvider;

class AssetPipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Vite::class, EarthCoopVite::class);
    }
}
