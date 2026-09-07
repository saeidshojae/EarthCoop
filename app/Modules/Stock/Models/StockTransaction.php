<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $table = 'stock_transactions';
    protected $fillable = ['user_id','auction_id','shares_count','price','price_gol','type','info'];
    protected $casts = ['shares_count'=>'integer','price_gol'=>'integer'];

    public function auction(){ return $this->belongsTo(Auction::class); }
    public function user(){ return $this->belongsTo(\App\Models\User::class); }

    public function getTotalGolAttribute(): ?int
    {
        if((int)($this->price_gol??0)<=0 || (int)$this->shares_count<=0) return null;
        if((int)$this->price_gol>intdiv(PHP_INT_MAX,(int)$this->shares_count)) return null;
        return (int)$this->price_gol*(int)$this->shares_count;
    }
}
