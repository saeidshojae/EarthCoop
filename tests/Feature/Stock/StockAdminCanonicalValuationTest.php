<?php

namespace Tests\Feature\Stock;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Modules\Stock\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdminCanonicalValuationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware([AdminMiddleware::class, PermissionMiddleware::class]);
    }

    public function test_admin_stock_form_is_bahar_facing_and_does_not_offer_rial_price_inputs(): void
    {
        $response = $this->get('/admin/stock/create');

        $response->assertOk();
        $response->assertSee('ارزش‌گذاری EarthCoop (بهار)');
        $response->assertSee('name="startup_valuation_bahar"', false);
        $response->assertSee('قیمت پایه هر سهم به‌صورت خودکار');
        $response->assertDontSee('ارزش پایه استارتاپ (ریال)');
        $response->assertDontSee('ارزش پایه هر سهم (ریال)');
        $response->assertDontSee('name="base_share_price"', false);
        $response->assertDontSee('مقادیر مالی در فرم و دیتابیس به صورت ریال هستند');
    }

    public function test_admin_can_persist_bahar_valuation_as_exact_canonical_gol(): void
    {
        $response = $this->post('/admin/stock', [
            'startup_valuation_bahar' => '12000000',
            'total_shares' => 100_000_000,
            'available_shares' => 10_000_000,
            'info' => 'Canonical EarthCoop valuation',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.stock.index'));

        $stock = Stock::query()->firstOrFail();
        $this->assertSame(1_200_000_000, (int) $stock->startup_valuation_gol);
        $this->assertSame(12, (int) $stock->base_share_price_gol);
        $this->assertSame(100_000_000, (int) $stock->total_shares);
        $this->assertSame(10_000_000, (int) $stock->available_shares);
    }

    public function test_admin_edit_form_reads_existing_canonical_value_back_as_bahar_not_legacy_decimal(): void
    {
        Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 999999999,
            'startup_valuation_gol' => 120_000,
            'total_shares' => 1_000,
            'base_share_price' => 999999,
            'base_share_price_gol' => 120,
            'available_shares' => 100,
        ]);

        $response = $this->get('/admin/stock/create');

        $response->assertOk();
        $response->assertSee('value="1200"', false);
        $response->assertDontSee('value="9999999990"', false);
    }

    public function test_admin_rejects_valuation_that_cannot_produce_exact_integer_gol_per_share(): void
    {
        $response = $this->from('/admin/stock/create')->post('/admin/stock', [
            'startup_valuation_bahar' => '1',
            'total_shares' => 3,
            'available_shares' => 1,
        ]);

        $response->assertRedirect('/admin/stock/create');
        $response->assertSessionHasErrors('startup_valuation_bahar');
        $this->assertDatabaseCount('stocks', 0);
    }
}
