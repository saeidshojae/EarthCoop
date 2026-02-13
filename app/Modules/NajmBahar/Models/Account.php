<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'najm_accounts';
    protected $fillable = [
        'account_number', // e.g. 1000000254 or 0000000000
        'user_id',
        'name',
        'type', // central|user|legal_entity|bank
        'balance',
        'balance_active',
        'balance_faded',
        'meta',
        'status'
    ];

    protected $casts = [
        'balance' => 'integer',
        'balance_active' => 'integer',
        'balance_faded' => 'integer',
        'meta' => 'array',
    ];

    public $timestamps = true;

    /**
     * تراکنش‌های خروجی
     */
    public function outgoingTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_account_id');
    }

    /**
     * تراکنش‌های ورودی
     */
    public function incomingTransactions()
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }
}
