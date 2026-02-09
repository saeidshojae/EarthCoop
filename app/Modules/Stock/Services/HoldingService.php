<?php
namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingTransaction;
use Illuminate\Support\Facades\DB;

class HoldingService
{
    public function getOrCreateHolding(int $userId, int $stockId): Holding
    {
        return Holding::firstOrCreate(
            ['user_id' => $userId, 'stock_id' => $stockId],
            ['quantity' => 0]
        );
    }
    
    public function credit(Holding $holding, int $quantity, string $description = null, $reference = null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding, $quantity, $description, $reference) {
            $holding->increment('quantity', $quantity);
            
            return $holding->transactions()->create([
                'type' => 'credit',
                'quantity' => $quantity,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function debit(Holding $holding, int $quantity, string $description = null, $reference = null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding, $quantity, $description, $reference) {
            if ($holding->quantity < $quantity) {
                throw new \Exception('Insufficient holdings');
            }
            
            $holding->decrement('quantity', $quantity);
            
            return $holding->transactions()->create([
                'type' => 'debit',
                'quantity' => $quantity,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function settlement(Holding $holding, int $quantity, string $description = null, $reference = null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding, $quantity, $description, $reference) {
            $holding->increment('quantity', $quantity);
            
            return $holding->transactions()->create([
                'type' => 'settlement',
                'quantity' => $quantity,
                'description' => $description,
                'ref_type' => $reference ? get_class($reference) : null,
                'ref_id' => $reference ? $reference->id : null,
            ]);
        });
    }
    
    public function getQuantity(int $userId, int $stockId): int
    {
        $holding = $this->getOrCreateHolding($userId, $stockId);
        return $holding->quantity;
    }
    
    public function getUserHoldings(int $userId)
    {
        return Holding::where('user_id', $userId)
            ->with('stock')
            ->where('quantity', '>', 0)
            ->get();
    }
}
