<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;

class AuctionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function settlement_never_oversells_available_shares()
    {
        // create stock with limited available shares
        $stock = Stock::create([
            'startup_valuation' => 100000,
            'total_shares' => 1000,
            'available_shares' => 5,
            'base_share_price' => 100,
            'info' => 'Test stock',
        ]);

        $auction = Auction::create([
            'stock_id' => $stock->id,
            'shares_count' => 5,
            'base_price' => 100,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addDay(),
            'status' => 'running',
            'type' => 'uniform_price',
            'lot_size' => 5,
        ]);

        $u1 = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'password' => bcrypt('secret')]);
        $u2 = User::create(['first_name' => 'C', 'last_name' => 'D', 'email' => 'b@example.com', 'password' => bcrypt('secret')]);

        // create bids that in total exceed available_shares
        Bid::create(['auction_id' => $auction->id, 'user_id' => $u1->id, 'price' => 200, 'quantity' => 3, 'status' => 'active']);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $u2->id, 'price' => 150, 'quantity' => 4, 'status' => 'active']);

        $service = app(\App\Modules\Stock\Services\AuctionService::class);

        $result = $service->closeAuction($auction);

        $stock->refresh();

        // available_shares must not be negative
        $this->assertGreaterThanOrEqual(0, $stock->available_shares);

        // total settled should be <= initial available shares (5)
        $this->assertLessThanOrEqual(5, $result['total_settled']);
    }
}
