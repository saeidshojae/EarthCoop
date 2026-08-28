<?php

namespace App\Providers;

use App\Modules\Stock\ExternalCapital\Adapters\UnavailableAuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Adapters\UnavailableExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use Illuminate\Support\ServiceProvider;

final class StockExternalCapitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthoritativeRateProvider::class, UnavailableAuthoritativeRateProvider::class);
        $this->app->bind(ExternalPaymentProvider::class, UnavailableExternalPaymentProvider::class);
    }
}
