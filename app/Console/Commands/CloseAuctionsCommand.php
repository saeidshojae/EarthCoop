<?php
namespace App\Console\Commands;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\AuctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseAuctionsCommand extends Command
{
    protected $signature = 'auctions:close';
    protected $description = 'Close expired auctions and settle them';
    
    protected $auctionService;
    
    public function __construct(AuctionService $auctionService)
    {
        parent::__construct();
        $this->auctionService = $auctionService;
    }
    
    public function handle()
    {
        $expiredAuctions = Auction::running()
            ->where('ends_at', '<=', now())
            ->get();
        
        $this->info("Found {$expiredAuctions->count()} expired auctions");
        
        foreach ($expiredAuctions as $auction) {
            try {
                $this->info("Closing auction #{$auction->id}...");
                
                $results = $this->auctionService->closeAuction($auction);
                
                $this->info("Auction #{$auction->id} closed successfully");
                $this->info("Winners: " . count($results['winners']));
                $this->info("Total settled: " . ($results['total_settled'] ?? 0));
                
                Log::info("Auction closed", [
                    'auction_id' => $auction->id,
                    'winners_count' => count($results['winners']),
                    'total_settled' => $results['total_settled'] ?? 0,
                ]);
                
            } catch (\Exception $e) {
                $this->error("Failed to close auction #{$auction->id}: " . $e->getMessage());
                
                Log::error("Failed to close auction", [
                    'auction_id' => $auction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->info("Auction closing process completed");
    }
}
