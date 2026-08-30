<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\ExternalCapital\Adapters\ServixGold24AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Adapters\ZarinpalExternalPaymentProvider;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ProductionExternalCapitalAdaptersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.providers.servix.api_key', 'servix-test-key');
        config()->set('stock.external_capital.providers.servix.base_url', 'https://servix.cc/api/v1');
        config()->set('stock.external_capital.providers.zarinpal.merchant_id', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
        config()->set('stock.external_capital.providers.zarinpal.base_url', 'https://api.zarinpal.com/pg/v4');
        config()->set('stock.external_capital.providers.zarinpal.gateway_url', 'https://www.zarinpal.com/pg/StartPay');
        config()->set('stock.external_capital.providers.zarinpal.callback_url', 'https://earthcoop.ir/stock/external-payment/callback');
        config()->set('stock.external_capital.providers.zarinpal.description', 'EarthCoop primary treasury share purchase');
    }

    public function test_servix_quotes_irr_directly_from_24k_gold_without_18k_conversion(): void
    {
        Http::fake([
            'https://servix.cc/api/v1/assets/GOLD_24_RLS' => Http::response([
                'code' => 'GOLD_24_RLS',
                'quoteUnit' => 'RLS',
                'value' => '288881000',
                'businessTime' => now()->subSeconds(5)->toIso8601String(),
            ], 200),
        ]);

        $quote = app(ServixGold24AuthoritativeRateProvider::class)->quote(1000, 'IRR');

        $this->assertSame('IRR', $quote->currency);
        $this->assertSame(288881000, $quote->fiatAmountMinor);
        $this->assertSame(288881000, $quote->rateNumerator);
        $this->assertSame(1000, $quote->rateDenominator);
        $this->assertSame('servix:gold24:irr:v1', $quote->source);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://servix.cc/api/v1/assets/GOLD_24_RLS'
                && $request->hasHeader('X-API-Key', 'servix-test-key');
        });
    }

    public function test_servix_rejects_any_payload_that_is_not_direct_24k_gold_in_rials(): void
    {
        Http::fake([
            'https://servix.cc/api/v1/assets/GOLD_24_RLS' => Http::response([
                'code' => 'GOLD_18_RLS',
                'quoteUnit' => 'RLS',
                'value' => '216660750',
                'businessTime' => now()->toIso8601String(),
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('24K');

        app(ServixGold24AuthoritativeRateProvider::class)->quote(1000, 'IRR');
    }

    public function test_servix_is_irr_only_and_usd_remains_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IRR');

        app(ServixGold24AuthoritativeRateProvider::class)->quote(1000, 'USD');
    }

    public function test_zarinpal_creates_only_irr_intents_and_exposes_redirect_url_with_intent_specific_callback(): void
    {
        Http::fake([
            'https://api.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => 'A00000000000000000000000000123456789',
                ],
                'errors' => [],
            ], 200),
        ]);

        $intent = ExternalPaymentIntent::make([
            'intent_key' => 'intent:zarinpal:1',
            'currency' => 'IRR',
            'amount_minor' => 288881000,
        ]);

        $providerIntent = app(ZarinpalExternalPaymentProvider::class)->createIntent($intent);

        $this->assertSame('A00000000000000000000000000123456789', $providerIntent->providerIntentId);
        $this->assertSame('IRR', $providerIntent->currency);
        $this->assertSame(288881000, $providerIntent->amountMinor);
        $this->assertSame(
            'https://www.zarinpal.com/pg/StartPay/A00000000000000000000000000123456789',
            $providerIntent->metadata['redirect_url']
        );

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.zarinpal.com/pg/v4/payment/request.json'
                && $data['merchant_id'] === 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
                && $data['amount'] === 288881000
                && $data['callback_url'] === 'https://earthcoop.ir/stock/external-payment/callback?intent=intent%3Azarinpal%3A1';
        });
    }

    public function test_zarinpal_verify_uses_canonical_intent_amount_not_callback_amount(): void
    {
        Http::fake([
            'https://api.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'ref_id' => 1234567890,
                    'card_hash' => 'safe-card-hash',
                ],
                'errors' => [],
            ], 200),
        ]);

        $intent = ExternalPaymentIntent::make([
            'intent_key' => 'intent:zarinpal:verify',
            'currency' => 'IRR',
            'amount_minor' => 288881000,
            'provider_intent_id' => 'A00000000000000000000000000123456789',
        ]);

        $event = app(ZarinpalExternalPaymentProvider::class)->verifyWebhook(
            $intent,
            json_encode([
                'Status' => 'OK',
                'Authority' => 'A00000000000000000000000000123456789',
                'amount' => 1,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame('payment_confirmed', $event->eventType);
        $this->assertSame('confirmed', $event->resultStatus);
        $this->assertSame(288881000, $event->amountMinor);
        $this->assertSame('IRR', $event->currency);
        $this->assertSame('1234567890', $event->providerPaymentId);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.zarinpal.com/pg/v4/payment/verify.json'
                && $data['authority'] === 'A00000000000000000000000000123456789'
                && $data['amount'] === 288881000
                && $data['amount'] !== 1;
        });
    }

    public function test_zarinpal_cancelled_callback_becomes_canonical_cancelled_event_without_verify_request(): void
    {
        Http::fake();

        $intent = ExternalPaymentIntent::make([
            'intent_key' => 'intent:zarinpal:cancelled',
            'currency' => 'IRR',
            'amount_minor' => 288881000,
            'provider_intent_id' => 'A00000000000000000000000000123456789',
        ]);

        $event = app(ZarinpalExternalPaymentProvider::class)->verifyWebhook(
            $intent,
            http_build_query([
                'Status' => 'NOK',
                'Authority' => 'A00000000000000000000000000123456789',
            ])
        );

        $this->assertSame('payment_cancelled', $event->eventType);
        $this->assertSame('cancelled', $event->resultStatus);
        $this->assertSame(288881000, $event->amountMinor);
        $this->assertSame('IRR', $event->currency);
        $this->assertNull($event->providerPaymentId);
        Http::assertNothingSent();
    }

    public function test_zarinpal_rejects_usd_intents(): void
    {
        $intent = ExternalPaymentIntent::make([
            'intent_key' => 'intent:zarinpal:usd',
            'currency' => 'USD',
            'amount_minor' => 100,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IRR');

        app(ZarinpalExternalPaymentProvider::class)->createIntent($intent);
    }
}
