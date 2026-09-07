<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class StockSettlementAllocation extends Model
{
    public const PREPARED = 'prepared';
    public const MONEY_CONFIRMED = 'money_confirmed';
    public const SETTLED = 'settled';
    public const RECONCILIATION_REQUIRED = 'reconciliation_required';
    public const CANCELLED = 'cancelled';

    protected $table = 'stock_settlement_allocations';
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'price_gol' => 'integer',
        'total_gol' => 'integer',
        'attempts' => 'integer',
        'metadata' => 'array',
        'settled_at' => 'datetime',
        'reconciliation_required_at' => 'datetime',
    ];

    public function auction(){ return $this->belongsTo(Auction::class); }
    public function bid(){ return $this->belongsTo(Bid::class); }
    public function stock(){ return $this->belongsTo(Stock::class); }
    public function externalPaymentIntent(){ return $this->belongsTo(ExternalPaymentIntent::class, 'external_payment_intent_id'); }
    public function holdingTransaction(){ return $this->belongsTo(HoldingTransaction::class, 'holding_transaction_id'); }
}
