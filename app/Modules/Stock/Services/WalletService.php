<?php
namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function getOrCreateWallet(int $userId, string $currency = 'IRR'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId, 'currency' => $currency],
            ['balance' => 0, 'held_amount' => 0]
        );
    }
    
    public function credit(Wallet $wallet, float $amount, string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet->increment('balance', $amount);
            
            return $wallet->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function debit(Wallet $wallet, float $amount, string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            if (!$wallet->canAfford($amount)) {
                throw new \Exception('Insufficient balance');
            }
            
            $wallet->decrement('balance', $amount);
            
            return $wallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function hold(Wallet $wallet, float $amount, string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            if (!$wallet->canAfford($amount)) {
                throw new \Exception('Insufficient balance for hold');
            }
            
            $wallet->increment('held_amount', $amount);
            
            return $wallet->transactions()->create([
                'type' => 'hold',
                'amount' => $amount,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function release(Wallet $wallet, float $amount, string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            if ($wallet->held_amount < $amount) {
                throw new \Exception('Insufficient held amount');
            }
            
            $wallet->decrement('held_amount', $amount);
            
            return $wallet->transactions()->create([
                'type' => 'release',
                'amount' => $amount,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    /**
     * Legacy wallet settlement only.
     *
     * This wallet is not Najm Bahar and must not be used as a second monetary
     * system. Until the canonical settlement gateways replace it, settlement
     * must at least consume both the reservation and the underlying legacy
     * balance atomically. The previous implementation only released the hold,
     * leaving balance unchanged after a purchase.
     */
    public function settle(Wallet $wallet, float $amount, string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedWallet->held_amount < $amount) {
                throw new \Exception('Insufficient held amount for settlement');
            }

            if ($lockedWallet->balance < $amount) {
                throw new \Exception('Insufficient balance for settlement');
            }

            $lockedWallet->decrement('held_amount', $amount);
            $lockedWallet->decrement('balance', $amount);
            $lockedWallet->refresh();

            // Keep the caller's model coherent with the row mutated under lock.
            $wallet->setRawAttributes($lockedWallet->getAttributes(), true);
            
            return $lockedWallet->transactions()->create([
                'type' => 'settlement',
                'amount' => $amount,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function getBalance(int $userId, string $currency = 'IRR'): float
    {
        $wallet = $this->getOrCreateWallet($userId, $currency);
        return $wallet->available_balance;
    }
    
    public function getHeldAmount(int $userId, string $currency = 'IRR'): float
    {
        $wallet = $this->getOrCreateWallet($userId, $currency);
        return $wallet->held_amount;
    }
}
