<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Bid extends Model
{
    protected $table = 'bids';
    protected $fillable = ['acceptance_key','auction_id','user_id','price','price_gol','reservation_key','external_payment_intent_id','quantity','status'];
    protected $casts = ['price'=>'decimal:2','price_gol'=>'integer','external_payment_intent_id'=>'integer','quantity'=>'integer'];

    protected static function booted(): void
    {
        static::creating(function (Bid $bid): void {
            $auction = Auction::query()->find($bid->auction_id);
            if (! $auction || ! $auction->hasCanonicalGolPricing()) return;

            if (blank($bid->acceptance_key) || (int)($bid->price_gol ?? 0) <= 0) {
                throw new RuntimeException('Canonical Gol auction bids must use the canonical bid acceptance service.');
            }

            if ((string)$auction->settlement_channel === \App\Modules\Stock\Settlement\SettlementChannel::ACTIVE_BAHAR
                && blank($bid->reservation_key)) {
                throw new RuntimeException('Canonical Active Bahar bid requires a reservation key before acceptance.');
            }
        });
    }

    protected function hasColumn(string $col): bool
    {
        try { return \Schema::hasColumn($this->getTable(),$col); }
        catch (\Exception $e) { return false; }
    }

    public function getPriceAttribute($value)
    {
        if ($value!==null) return $value;
        if ($this->hasColumn('bid_price')&&array_key_exists('bid_price',$this->attributes)) return $this->attributes['bid_price'];
        return null;
    }

    public function setPriceAttribute($value)
    {
        if($this->hasColumn('price')) $this->attributes['price']=$value;
        elseif($this->hasColumn('bid_price')) $this->attributes['bid_price']=$value;
        else $this->attributes['price']=$value;
    }

    public function getQuantityAttribute($value)
    {
        if($value!==null) return $value;
        if($this->hasColumn('shares_count')&&array_key_exists('shares_count',$this->attributes)) return (int)$this->attributes['shares_count'];
        return null;
    }

    public function setQuantityAttribute($value)
    {
        if($this->hasColumn('quantity')) $this->attributes['quantity']=$value;
        elseif($this->hasColumn('shares_count')) $this->attributes['shares_count']=$value;
        else $this->attributes['quantity']=$value;
    }

    public function auction(): BelongsTo { return $this->belongsTo(Auction::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function externalPaymentIntent(): BelongsTo { return $this->belongsTo(ExternalPaymentIntent::class,'external_payment_intent_id'); }
    public function getTotalValueAttribute(): float { return $this->price*$this->quantity; }

    public function getTotalGolAttribute(): ?int
    {
        if((int)($this->price_gol??0)<=0 || (int)$this->quantity<=0) return null;
        if((int)$this->price_gol>intdiv(PHP_INT_MAX,(int)$this->quantity)) return null;
        return (int)$this->price_gol*(int)$this->quantity;
    }

    public function scopeActive($query){ return $query->where('status','active'); }
    public function scopeWon($query){ return $query->where('status','won'); }
    public function scopeLost($query){ return $query->where('status','lost'); }

    /** Legacy decimal ordering retained until the legacy AuctionService is retired. */
    public function scopeByPrice($query,$direction='desc')
    {
        try {
            if(\Schema::hasColumn($this->getTable(),'price')) return $query->orderBy('price',$direction);
            if(\Schema::hasColumn($this->getTable(),'bid_price')) return $query->orderBy('bid_price',$direction);
        } catch (\Exception $e) {}
        return $query;
    }

    public function scopeByCanonicalPrice($query,$direction='desc')
    {
        return $query->whereNotNull('price_gol')->orderBy('price_gol',$direction);
    }
}
