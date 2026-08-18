<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\AuctionService;
use App\Modules\Stock\Services\WalletService;

class AuctionConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function settlement_never_oversells_available_shares()
    {
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
            'ends_at' => now()->addDay(),
            'status' => 'running',
            'type' => 'uniform_price',
            'lot_size' => 5,
        ]);

        $u1 = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'password' => bcrypt('secret')]);
        $u2 = User::create(['first_name' => 'C', 'last_name' => 'D', 'email' => 'b@example.com', 'password' => bcrypt('secret')]);

        $service = app(AuctionService::class);
        $walletService = app(WalletService::class);

        // Enter bids through the real reservation path. Direct Bid::create would
        // bypass held funds and make settlement fail for an unrelated reason.
        $walletService->credit($walletService->getOrCreateWallet($u1->id), 1000, 'Auction concurrency fixture');
        $walletService->credit($walletService->getOrCreateWallet($u2->id), 1000, 'Auction concurrency fixture');

        $service->validateAndPlaceBid($u1->id, $auction, 200, 3);
        $service->validateAndPlaceBid($u2->id, $auction, 150, 4);

        $result = $service->closeAuction($auction);

        $stock->refresh();

        $this->assertGreaterThanOrEqual(0, $stock->available_shares);
        $this->assertLessThanOrEqual(5, $result['total_settled']);
    }
}
