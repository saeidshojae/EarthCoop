<?php

namespace App\Providers;

use App\Modules\Stock\ExternalCapital\Adapters\ServixGold24AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Adapters\UnavailableAuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Adapters\UnavailableExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Adapters\ZarinpalExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use Illuminate\Support\ServiceProvider;

final class StockExternalCapitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $rateProvider = trim((string) config('stock.external_capital.rate_provider', 'unavailable'));
        $paymentProvider = trim((string) config('stock.external_capital.payment_provider', 'unavailable'));

        $this->app->bind(
            AuthoritativeRateProvider::class,
            $rateProvider === 'servix_gold24'
                ? ServixGold24AuthoritativeRateProvider::class
                : UnavailableAuthoritativeRateProvider::class,
        );

        $this->app->bind(
            ExternalPaymentProvider::class,
            $paymentProvider === 'zarinpal'
                ? ZarinpalExternalPaymentProvider::class
                : UnavailableExternalPaymentProvider::class,
        );
    }
}
