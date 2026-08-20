<?php
namespace App\Console\Commands;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\AuctionService;
use App\Modules\Stock\Services\CanonicalAuctionCloseService;
use App\Modules\Stock\Services\SecondaryAuctionCloseService;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseAuctionsCommand extends Command
{
    protected $signature='auctions:close';
    protected $description='Close expired legacy/canonical auctions through their authoritative settlement engine';

    public function __construct(
        protected AuctionService $legacy,
        protected CanonicalAuctionCloseService $primaryCanonical,
        protected SecondaryAuctionCloseService $secondaryCanonical,
    ) { parent::__construct(); }

    public function handle(): int
    {
        $expired=Auction::running()->where('ends_at','<=',now())->with('stock')->get();
        $this->info("Found {$expired->count()} expired auctions");

        foreach($expired as $auction){
            try {
                $results=$this->close($auction);
                $this->info("Auction #{$auction->id}: ".($results['status']??'closed'));
                Log::info('Auction close dispatched',['auction_id'=>$auction->id,'canonical'=>$auction->hasCanonicalGolPricing(),'result'=>$this->safeResult($results)]);
            } catch(\Throwable $e){
                $this->error("Failed auction #{$auction->id}: {$e->getMessage()}");
                Log::error('Auction close failed',['auction_id'=>$auction->id,'canonical'=>$auction->hasCanonicalGolPricing(),'error'=>$e->getMessage()]);
            }
        }
        return self::SUCCESS;
    }

    private function close(Auction $auction): array
    {
        if(!$auction->hasCanonicalGolPricing()) return $this->legacy->closeAuction($auction);

        $market=(string)$auction->market_type;
        $supply=(string)$auction->supply_source;
        $channel=(string)$auction->settlement_channel;
        $issuer=(string)($auction->stock?->issuer_type??'');

        // Feature flags gate creation of new commitments. Existing canonical
        // auctions must always be allowed to finish their accepted lifecycle.
        if($market===SettlementEligibilityPolicy::MARKET_SECONDARY
            && $supply===SettlementEligibilityPolicy::SUPPLY_HOLDER
            && $channel===SettlementChannel::ACTIVE_BAHAR){
            return $this->secondaryCanonical->close($auction);
        }

        if($market===SettlementEligibilityPolicy::MARKET_PRIMARY
            && $supply===SettlementEligibilityPolicy::SUPPLY_TREASURY
            && $channel===SettlementChannel::ACTIVE_BAHAR){
            if($issuer!==SettlementEligibilityPolicy::ISSUER_EARTHCOOP){
                return [
                    'status'=>'blocked',
                    'reason'=>$issuer===SettlementEligibilityPolicy::ISSUER_PROJECT?'project_payee_mapping_missing':'unsupported_primary_issuer',
                    'auction_id'=>$auction->id,
                ];
            }
            return $this->primaryCanonical->close($auction);
        }

        return ['status'=>'blocked','reason'=>'no_enabled_canonical_close_engine','auction_id'=>$auction->id,'settlement_channel'=>$channel];
    }

    private function safeResult(array $result): array
    {
        return array_intersect_key($result,array_flip(['status','reason','auction_id','allocated_shares','clearing_price_gol']));
    }
}
