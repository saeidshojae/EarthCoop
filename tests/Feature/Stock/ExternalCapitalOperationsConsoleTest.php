<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\ExternalPaymentReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExternalCapitalOperationsConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the real admin boundary instead of selectively disabling one
        // middleware class. The console lives inside the canonical admin stack,
        // so its feature tests must authenticate with the same contract as the UI.
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_admin_operations_routes_are_registered(): void
    {
        $index = Route::getRoutes()->match(Request::create('/admin/stock/external-payments', 'GET'));
        $show = Route::getRoutes()->match(Request::create('/admin/stock/external-payments/1', 'GET'));

        $this->assertSame('admin.stock.external-payments.index', $index->getName());
        $this->assertSame('admin.stock.external-payments.show', $show->getName());
    }

    public function test_index_lists_operational_payment_state_without_provider_secrets(): void
    {
        $intent = $this->intent();

        $response = $this->get('/admin/stock/external-payments');

        $response->assertOk();
        $response->assertSee('تسویه‌های خارجی');
        $response->assertSee($intent->intent_key);
        $response->assertSee('pending');
        $response->assertSee('IRR');
        $response->assertSee('zarinpal');
        $response->assertDontSee('merchant-secret-value');
        $response->assertDontSee('api-secret-value');
    }

    public function test_show_exposes_append_only_reconciliation_history_but_no_payment_mutation_controls(): void
    {
        $intent = $this->intent();
        ExternalPaymentReconciliation::create([
            'payment_intent_id' => $intent->id,
            'event_key' => 'event:ops:pending',
            'provider' => 'zarinpal',
            'provider_event_id' => 'authority-safe-id',
            'provider_payment_id' => null,
            'event_type' => 'payment_pending',
            'currency' => 'IRR',
            'amount_minor' => 250000,
            'result_status' => 'pending',
            'provider_payload' => ['authority' => 'safe-authority'],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $response = $this->get('/admin/stock/external-payments/' . $intent->id);

        $response->assertOk();
        $response->assertSee('تاریخچه تطبیق');
        $response->assertSee('payment_pending');
        $response->assertSee('authority-safe-id');
        $response->assertSee('فقط خواندنی');

        // The unified admin layout legitimately owns a logout form. The console
        // contract is narrower: it must expose no payment-state mutation controls.
        $response->assertDontSee('تأیید پرداخت');
        $response->assertDontSee('تایید پرداخت');
        $response->assertDontSee('لغو پرداخت');
        $response->assertDontSee('بازپرداخت');
        $response->assertDontSee('برگشت پرداخت');
        $response->assertDontSee('merchant-secret-value');
        $response->assertDontSee('api-secret-value');
    }

    private function intent(): ExternalPaymentIntent
    {
        config()->set('stock.external_capital.zarinpal.merchant_id', 'merchant-secret-value');
        config()->set('stock.external_capital.servix.api_key', 'api-secret-value');

        return ExternalPaymentIntent::create([
            'channel' => 'external_irr',
            'currency' => 'IRR',
            'amount_minor' => 250000,
            'status' => ExternalPaymentIntent::PENDING,
            'intent_key' => 'intent:ops:1',
            'reference_type' => 'auction_bid',
            'reference_id' => '42',
            'provider' => 'zarinpal',
            'provider_intent_id' => 'authority-safe-id',
            'quote_snapshot' => [
                'currency' => 'IRR',
                'fiat_amount_minor' => 250000,
                'source' => 'servix:gold24:irr:v1',
            ],
            'metadata' => [],
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
