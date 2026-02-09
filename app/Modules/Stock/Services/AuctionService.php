<?php
namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Models\Holding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuctionService
{
    protected $walletService;
    protected $holdingService;
    
    public function __construct(WalletService $walletService, HoldingService $holdingService)
    {
        $this->walletService = $walletService;
        $this->holdingService = $holdingService;
    }
    
    public function validateAndPlaceBid(int $userId, Auction $auction, float $price, int $quantity): Bid
    {
        return DB::transaction(function () use ($userId, $auction, $price, $quantity) {
            // Validate auction is active
            if (!$auction->isActive()) {
                throw new \Exception('Auction is not active');
            }
            
            // Validate price range
            if ($auction->min_bid && $price < $auction->min_bid) {
                throw new \Exception('Bid price below minimum');
            }
            
            if ($auction->max_bid && $price > $auction->max_bid) {
                throw new \Exception('Bid price above maximum');
            }
            
            // Validate quantity
            if ($quantity > $auction->lot_size) {
                throw new \Exception('Quantity exceeds lot size');
            }
            
            // Check wallet balance
            $wallet = $this->walletService->getOrCreateWallet($userId);
            $totalAmount = $price * $quantity;
            
            if (!$wallet->canAfford($totalAmount)) {
                throw new \Exception('Insufficient wallet balance');
            }
            
            // Hold the amount
            $this->walletService->hold($wallet, $totalAmount, "Bid for auction #{$auction->id}", $auction);
            
            // Create bid
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $userId,
                'price' => $price,
                'quantity' => $quantity,
                'status' => 'active',
            ]);

            // Reputation: bid placed
            try {
                $reputation = app(\App\Services\ReputationService::class);
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $reputation->applyAction($user, 'bid_placed', ['auction_id' => $auction->id, 'bid_id' => $bid->id], $bid->id, 'stock.bid');
                }
            } catch (\Exception $e) {
                \Log::warning('Reputation bid_placed failed: ' . $e->getMessage());
            }
            
            // ارسال اعلان به ادمین‌ها در صورت پیشنهاد جدید
            try {
                $notificationService = app(\App\Services\NotificationService::class);
                $admins = \App\Models\User::where('is_admin', true)->orWhereHas('roles', function($q) {
                    $q->whereIn('slug', ['super-admin', 'stock-manager']);
                })->get();
                
                if ($admins->count() > 0) {
                    $notificationService->notifyMany(
                        $admins,
                        'پیشنهاد جدید ثبت شد',
                        "پیشنهاد جدید در حراج #{$auction->id} با قیمت " . number_format($price) . " تومان ثبت شد.",
                        route('admin.auction.show', $auction),
                        'auction.bid',
                        ['auction_id' => $auction->id, 'bid_id' => $bid->id]
                    );
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to send bid notification: ' . $e->getMessage());
            }
            
            // ارسال اعلان به کاربرانی که پیشنهادشان بالاتر زده شده
            try {
                $notificationService = app(\App\Services\NotificationService::class);
                
                // پیدا کردن کاربرانی که پیشنهاد فعال دارند و پیشنهادشان کمتر از این پیشنهاد جدید است
                $outbidUsers = $auction->activeBids()
                    ->where('user_id', '!=', $userId)
                    ->where('price', '<', $price)
                    ->distinct('user_id')
                    ->pluck('user_id')
                    ->all();
                
                if (!empty($outbidUsers)) {
                    $notificationService->notifyMany(
                        $outbidUsers,
                        'پیشنهاد بالاتری ثبت شد',
                        "در حراج #{$auction->id} پیشنهاد بالاتری با قیمت " . number_format($price) . " تومان ثبت شد. می‌توانید پیشنهاد خود را افزایش دهید.",
                        route('auction.show', $auction),
                        'auction.outbid',
                        ['auction_id' => $auction->id, 'bid_id' => $bid->id, 'new_price' => $price]
                    );
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to send outbid notification: ' . $e->getMessage());
            }
            
            return $bid;
        });
    }
    
    public function closeAuction(Auction $auction): array
    {
        return DB::transaction(function () use ($auction) {
            // اگر settlement_mode = manual باشد، فقط وضعیت را به settling تغییر می‌دهیم
            if ($auction->settlement_mode === 'manual') {
                $auction->update(['status' => 'settling']);
                return [
                    'winners' => [],
                    'total_settled' => 0,
                    'requires_manual_approval' => true,
                ];
            }
            
            // تسویه خودکار
            $auction->update(['status' => 'settling']);
            
            $results = [];
            
            switch ($auction->type) {
                case 'single_winner':
                    $results = $this->settleSingleWinner($auction);
                    break;
                case 'uniform_price':
                    $results = $this->settleUniformPrice($auction);
                    break;
                case 'pay_as_bid':
                    $results = $this->settlePayAsBid($auction);
                    break;
            }
            
            $auction->update(['status' => 'settled']);
            
            return $results;
        });
    }
    
    // تسویه دستی حراج (توسط ادمین)
    public function manualSettleAuction(Auction $auction): array
    {
        if ($auction->status !== 'settling') {
            throw new \Exception('فقط حراج‌های در حال تسویه قابل تایید هستند');
        }
        
        return DB::transaction(function () use ($auction) {
            $results = [];
            
            switch ($auction->type) {
                case 'single_winner':
                    $results = $this->settleSingleWinner($auction);
                    break;
                case 'uniform_price':
                    $results = $this->settleUniformPrice($auction);
                    break;
                case 'pay_as_bid':
                    $results = $this->settlePayAsBid($auction);
                    break;
            }
            
            $auction->update(['status' => 'settled']);
            
            return $results;
        });
    }
    
    protected function settleSingleWinner(Auction $auction): array
    {
        // Lock stock row to prevent concurrent settlements from overselling
        $stock = \App\Modules\Stock\Models\Stock::where('id', $auction->stock_id)->lockForUpdate()->first();

        // determine which price column exists to avoid SQL errors on older schemas
        try {
            $priceColumn = Schema::hasColumn('bids', 'price') ? 'price' : (Schema::hasColumn('bids', 'bid_price') ? 'bid_price' : null);
        } catch (\Exception $e) {
            $priceColumn = null;
        }

        if ($priceColumn) {
            $winningBid = $auction->activeBids()
                ->orderByDesc($priceColumn)
                ->orderBy('created_at', 'asc')
                ->first();
        } else {
            // fallback to in-memory collection ordering to be resilient to schema differences
            $winningBid = $auction->bids->where('status', 'active')
                ->sort(function($a, $b) {
                    $priceA = $a->price ?? 0;
                    $priceB = $b->price ?? 0;
                    if ($priceA == $priceB) {
                        return strtotime($a->created_at) <=> strtotime($b->created_at);
                    }
                    return $priceB <=> $priceA;
                })->values()->first();
        }
        
        if (!$winningBid) {
            return ['winners' => [], 'total_settled' => 0];
        }
        
        $winningBid->update(['status' => 'won']);
        
        // Release other bids
        $auction->activeBids()
            ->where('id', '!=', $winningBid->id)
            ->update(['status' => 'lost']);
        
        $this->releaseOtherBids($auction, $winningBid->id);
        
        // Determine allocation respecting stock available_shares
        $alloc = min($winningBid->quantity, $stock->available_shares);
        if ($alloc <= 0) {
            // no shares left, mark as lost and release funds
            $winningBid->update(['status' => 'lost']);
            $this->walletService->release(
                $this->walletService->getOrCreateWallet($winningBid->user_id),
                $winningBid->total_value,
                "Bid not filled - auction #{$auction->id}",
                $winningBid
            );
            return ['winners' => [], 'total_settled' => 0];
        }

        // Settle winning portion
        if ($alloc < $winningBid->quantity) {
            // partial allocation
            $this->settlePartialBid($winningBid, $alloc, $winningBid->price);
        } else {
            $this->settleWinningBid($winningBid);
        }
        
        // ارسال اعلان به برنده
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->notifyUser(
                $winningBid->user_id,
                'تبریک! شما برنده حراج شدید',
                "شما در حراج #{$auction->id} برنده شدید. قیمت پیشنهادی شما: " . number_format($winningBid->price) . " تومان برای " . number_format($winningBid->quantity) . " سهم.",
                route('auction.show', $auction),
                'auction.won',
                ['auction_id' => $auction->id, 'bid_id' => $winningBid->id, 'price' => $winningBid->price, 'quantity' => $winningBid->quantity]
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send winner notification: ' . $e->getMessage());
        }
        
        return [
            'winners' => [$winningBid],
            'total_settled' => $winningBid->quantity,
        ];
    }
    
    protected function settleUniformPrice(Auction $auction): array
    {
        // Lock stock row to prevent concurrent settlements
        $stock = \App\Modules\Stock\Models\Stock::where('id', $auction->stock_id)->lockForUpdate()->first();

        // fetch bids ordered by price desc / created_at asc, with fallback to collection sort
        try {
            $priceColumn = Schema::hasColumn('bids', 'price') ? 'price' : (Schema::hasColumn('bids', 'bid_price') ? 'bid_price' : null);
        } catch (\Exception $e) {
            $priceColumn = null;
        }

        if ($priceColumn) {
            $bids = $auction->activeBids()->orderByDesc($priceColumn)->orderBy('created_at', 'asc')->get();
        } else {
            $bids = $auction->bids->where('status', 'active')->sort(function($a, $b) {
                $priceA = $a->price ?? 0;
                $priceB = $b->price ?? 0;
                if ($priceA == $priceB) {
                    return strtotime($a->created_at) <=> strtotime($b->created_at);
                }
                return $priceB <=> $priceA;
            })->values();
        }
        
        $winners = [];
        $remainingShares = $auction->shares_count;
        $clearingPrice = 0;
        
        foreach ($bids as $bid) {
            if ($remainingShares <= 0) {
                $bid->update(['status' => 'lost']);
                $this->walletService->release(
                    $this->walletService->getOrCreateWallet($bid->user_id),
                    $bid->total_value,
                    "Bid not filled - auction #{$auction->id}",
                    $bid
                );
                continue;
            }
            
            // ensure we don't allocate more than stock available_shares
            $allocatedShares = min($bid->quantity, $remainingShares, $stock->available_shares);
            $remainingShares -= $allocatedShares;
            
            if ($allocatedShares > 0) {
                $bid->update(['status' => 'won']);
                $winners[] = $bid;
                $clearingPrice = $bid->price;
                
                // Settle partial allocation (this will decrement stock available_shares)
                $this->settlePartialBid($bid, $allocatedShares, $clearingPrice);
                // refresh stock available_shares in memory
                $stock->refresh();
            }
        }
        
        // ارسال اعلان به برندگان
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            foreach ($winners as $winningBid) {
                $notificationService->notifyUser(
                    $winningBid->user_id,
                    'تبریک! شما برنده حراج شدید',
                    "شما در حراج #{$auction->id} برنده شدید. قیمت پیشنهادی شما: " . number_format($winningBid->price) . " تومان برای " . number_format($winningBid->quantity) . " سهم.",
                    route('auction.show', $auction),
                    'auction.won',
                    ['auction_id' => $auction->id, 'bid_id' => $winningBid->id, 'price' => $winningBid->price, 'quantity' => $winningBid->quantity]
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send winner notifications: ' . $e->getMessage());
        }
        
        return [
            'winners' => $winners,
            'total_settled' => $auction->shares_count - $remainingShares,
            'clearing_price' => $clearingPrice,
        ];
    }
    
    protected function settlePayAsBid(Auction $auction): array
    {
        // Lock stock row to prevent concurrent settlements
        $stock = \App\Modules\Stock\Models\Stock::where('id', $auction->stock_id)->lockForUpdate()->first();

        try {
            $priceColumn = Schema::hasColumn('bids', 'price') ? 'price' : (Schema::hasColumn('bids', 'bid_price') ? 'bid_price' : null);
        } catch (\Exception $e) {
            $priceColumn = null;
        }

        if ($priceColumn) {
            $bids = $auction->activeBids()->orderByDesc($priceColumn)->orderBy('created_at', 'asc')->get();
        } else {
            $bids = $auction->bids->where('status', 'active')->sort(function($a, $b) {
                $priceA = $a->price ?? 0;
                $priceB = $b->price ?? 0;
                if ($priceA == $priceB) {
                    return strtotime($a->created_at) <=> strtotime($b->created_at);
                }
                return $priceB <=> $priceA;
            })->values();
        }
        
        $winners = [];
        $remainingShares = $auction->shares_count;
        
        foreach ($bids as $bid) {
            if ($remainingShares <= 0) {
                $bid->update(['status' => 'lost']);
                $wallet = $this->walletService->getOrCreateWallet($bid->user_id);
                $user = \App\Models\User::find($bid->user_id);
                
                $this->walletService->release(
                    $wallet,
                    $bid->total_value,
                    "Bid not filled - auction #{$auction->id}",
                    $bid
                );
                
                // Dispatch events
                if ($user) {
                    event(new \App\Events\BidLost($bid, $auction, $user));
                    event(new \App\Events\WalletReleased($wallet, $user, $bid->total_value, $bid, "Bid not filled - auction #{$auction->id}"));
                }
                
                continue;
            }
            
            $allocatedShares = min($bid->quantity, $remainingShares, $stock->available_shares);
            $remainingShares -= $allocatedShares;
            
            if ($allocatedShares > 0) {
                $bid->update(['status' => 'won']);
                $winners[] = $bid;
                
                // Settle at bid price
                $this->settlePartialBid($bid, $allocatedShares, $bid->price);
                $stock->refresh();
            }
        }
        
        // ارسال اعلان به برندگان
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            foreach ($winners as $winningBid) {
                $notificationService->notifyUser(
                    $winningBid->user_id,
                    'تبریک! شما برنده حراج شدید',
                    "شما در حراج #{$auction->id} برنده شدید. قیمت پیشنهادی شما: " . number_format($winningBid->price) . " تومان برای " . number_format($winningBid->quantity) . " سهم.",
                    route('auction.show', $auction),
                    'auction.won',
                    ['auction_id' => $auction->id, 'bid_id' => $winningBid->id, 'price' => $winningBid->price, 'quantity' => $winningBid->quantity]
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send winner notifications: ' . $e->getMessage());
        }
        
        return [
            'winners' => $winners,
            'total_settled' => $auction->shares_count - $remainingShares,
        ];
    }
    
    protected function settleWinningBid(Bid $bid): void
    {
        $wallet = $this->walletService->getOrCreateWallet($bid->user_id);
        $holding = $this->holdingService->getOrCreateHolding($bid->user_id, $bid->auction->stock_id);
        
        // Settle payment
        $this->walletService->settle($wallet, $bid->total_value, "Auction settlement", $bid);
        
        // Transfer shares
        $this->holdingService->settlement($holding, $bid->quantity, "Auction win", $bid);
        
        // Dispatch events
        $user = \App\Models\User::find($bid->user_id);
        if ($user) {
            event(new \App\Events\WalletSettled($wallet, $user, $bid->total_value, $bid, "Auction settlement"));
            event(new \App\Events\SharesReceived($holding, $user, $bid->quantity, $bid->auction, $bid, "Auction win"));
        }
        
        // Record stock transaction and update stock availability
        \App\Modules\Stock\Models\StockTransaction::create([
            'user_id' => $bid->user_id,
            'auction_id' => $bid->auction_id,
            'shares_count' => $bid->quantity,
            'price' => $bid->price,
            'type' => 'buy',
            'info' => 'Settlement - single winner',
        ]);

        $stock = $bid->auction->stock;
        if ($stock) {
            $stock->decrement('available_shares', $bid->quantity);
            $stock->recalculateMarketData();
        }

        // Reputation: bid won and successful settlement
        try {
            $reputation = app(\App\Services\ReputationService::class);
            $user = \App\Models\User::find($bid->user_id);
            if ($user) {
                $reputation->applyAction($user, 'bid_won', ['auction_id' => $bid->auction_id, 'bid_id' => $bid->id], $bid->id, 'stock.bid');
                $reputation->applyAction($user, 'successful_settlement', ['auction_id' => $bid->auction_id, 'bid_id' => $bid->id, 'shares' => $bid->quantity], $bid->id, 'stock.settlement');
            }
        } catch (\Exception $e) {
            \Log::warning('Reputation settlement notifications failed: ' . $e->getMessage());
        }
    }
    
    protected function settlePartialBid(Bid $bid, int $allocatedShares, float $price): void
    {
        $wallet = $this->walletService->getOrCreateWallet($bid->user_id);
        $holding = $this->holdingService->getOrCreateHolding($bid->user_id, $bid->auction->stock_id);
        
        $totalAmount = $price * $allocatedShares;
        $remainingAmount = $bid->total_value - $totalAmount;
        
        // Settle payment for allocated shares
        $this->walletService->settle($wallet, $totalAmount, "Auction settlement", $bid);
        
        // Release remaining amount
        if ($remainingAmount > 0) {
            $this->walletService->release($wallet, $remainingAmount, "Unallocated bid amount", $bid);
        }
        
        // Transfer allocated shares
        $this->holdingService->settlement($holding, $allocatedShares, "Auction win", $bid);
        
        // Dispatch events
        $user = \App\Models\User::find($bid->user_id);
        if ($user) {
            event(new \App\Events\WalletSettled($wallet, $user, $totalAmount, $bid, "Auction settlement"));
            if ($remainingAmount > 0) {
                event(new \App\Events\WalletReleased($wallet, $user, $remainingAmount, $bid, "Unallocated bid amount"));
            }
            event(new \App\Events\SharesReceived($holding, $user, $allocatedShares, $bid->auction, $bid, "Auction win"));
        }

        // Record partial stock transaction and update stock availability
        \App\Modules\Stock\Models\StockTransaction::create([
            'user_id' => $bid->user_id,
            'auction_id' => $bid->auction_id,
            'shares_count' => $allocatedShares,
            'price' => $price,
            'type' => 'buy',
            'info' => 'Partial settlement',
        ]);

        $stock = $bid->auction->stock;
        if ($stock) {
            $stock->decrement('available_shares', $allocatedShares);
            $stock->recalculateMarketData();
        }

        // Reputation: partial settlement -> award bid_won and successful_settlement for allocated portion
        try {
            $reputation = app(\App\Services\ReputationService::class);
            $user = \App\Models\User::find($bid->user_id);
            if ($user) {
                // award bid_won once (even for partial) and a settlement reward
                $reputation->applyAction($user, 'bid_won', ['auction_id' => $bid->auction_id, 'bid_id' => $bid->id, 'allocated_shares' => $allocatedShares], $bid->id, 'stock.bid');
                $reputation->applyAction($user, 'successful_settlement', ['auction_id' => $bid->auction_id, 'bid_id' => $bid->id, 'shares' => $allocatedShares], $bid->id, 'stock.settlement');
            }
        } catch (\Exception $e) {
            \Log::warning('Reputation partial settlement failed: ' . $e->getMessage());
        }
    }
    
    protected function releaseOtherBids(Auction $auction, int $excludeBidId): void
    {
        $auction->activeBids()
            ->where('id', '!=', $excludeBidId)
            ->get()
            ->each(function ($bid) use ($auction) {
                $wallet = $this->walletService->getOrCreateWallet($bid->user_id);
                $user = \App\Models\User::find($bid->user_id);
                
                // Release funds
                $this->walletService->release(
                    $wallet,
                    $bid->total_value,
                    "Bid not filled - auction #{$auction->id}",
                    $bid
                );
                
                // Dispatch events
                if ($user) {
                    event(new \App\Events\BidLost($bid, $auction, $user));
                    event(new \App\Events\WalletReleased($wallet, $user, $bid->total_value, $bid, "Bid not filled - auction #{$auction->id}"));
                }
            });
    }
}
