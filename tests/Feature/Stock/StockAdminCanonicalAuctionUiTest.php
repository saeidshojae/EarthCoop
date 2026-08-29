<?php

namespace Tests\Feature\Stock;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Modules\Stock\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdminCanonicalAuctionUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_primary_auction_form_is_gol_bahar_facing_and_not_rial_priced(): void
    {
        $this->actingAs(User::factory()->create());
        $this->withoutMiddleware([AdminMiddleware::class, PermissionMiddleware::class]);

        Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ]);

        $response = $this->get('/admin/auctions/create');

        $response->assertOk();
        $response->assertSee('عرضه اولیه خزانه EarthCoop');
        $response->assertSee('قیمت پایه هر سهم (گل)');
        $response->assertSee('name="base_price_gol"', false);
        $response->assertSee('حداکثر عرضه اولیه: ۱۰٪');
        $response->assertSee('تسویه خارجی فقط برای عرضه اولیه خزانه EarthCoop');
        $response->assertDontSee('قیمت پایه هر سهم (ریال)');
        $response->assertDontSee('name="base_price"', false);
    }
}
