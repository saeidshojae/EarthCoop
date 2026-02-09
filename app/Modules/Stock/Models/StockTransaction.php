<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $table = 'stock_transactions';
    protected $fillable = [
        'user_id',
        'auction_id',
        'shares_count',
        'price',
        'type', // buy/sell
        'info',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
