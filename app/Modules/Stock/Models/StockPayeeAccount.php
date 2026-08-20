<?php

namespace App\Modules\Stock\Models;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use Illuminate\Database\Eloquent\Model;

class StockPayeeAccount extends Model
{
    protected $fillable = [
        'stock_id','account_id','purpose','is_active','configured_by','verified_at','metadata',
    ];

    protected $casts = [
        'is_active'=>'boolean','verified_at'=>'datetime','metadata'=>'array',
    ];

    public function stock(){ return $this->belongsTo(Stock::class); }
    public function account(){ return $this->belongsTo(Account::class); }
    public function configuredBy(){ return $this->belongsTo(User::class,'configured_by'); }
}
