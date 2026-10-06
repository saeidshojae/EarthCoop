<?php

namespace Tests\Feature\Temporal;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdminAuctionLocalizedDateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_admin_can_store_auction_with_jalali_local_datetimes(): void
    {
        $this->withoutMiddleware();
        app()->setLocale('fa');

        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ]);

        $payload = [
            'stock_id' => $stock->id,
            'shares_count' => 500_000,
            'base_price_gol' => 12,
            'settlement_channel' => SettlementChannel::ACTIVE_BAHAR,
            'start_time' => '۱۴۰۵/۰۷/۱۰ ۱۰:۳۰',
            'end_time' => '۱۴۰۵/۰۷/۱۱ ۱۰:۳۰',
            'ends_at' => '۱۴۰۵/۰۷/۱۱ ۱۰:۳۰',
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ];

        $response = $this->post('/admin/auctions', $payload);

        $response->assertSessionHasNoErrors();
        $auction = Auction::query()->firstOrFail();

        $temporal = app(TemporalService::class);
        $context = app(TemporalContextResolver::class)->defaultContext();

        $this->assertSame(
            $temporal->parseDateTime($payload['start_time'], $context)->format('Y-m-d H:i:s'),
            $auction->getRawOriginal('start_time'),
        );
        $this->assertSame(
            $temporal->parseDateTime($payload['end_time'], $context)->format('Y-m-d H:i:s'),
            $auction->getRawOriginal('end_time'),
        );
        $this->assertSame(
            $temporal->parseDateTime($payload['ends_at'], $context)->format('Y-m-d H:i:s'),
            $auction->getRawOriginal('ends_at'),
        );
    }
}
