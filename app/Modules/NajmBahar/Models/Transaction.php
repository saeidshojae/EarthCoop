<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'najm_transactions';
    protected $fillable = [
        'tracking_number',
        'from_account_id',
        'to_account_id',
        'amount',
        'type', // immediate|scheduled|fee|adjustment
        'status', // pending|completed|failed
        'scheduled_at',
        'metadata',
        'description'
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public $timestamps = true;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (!$transaction->tracking_number) {
                // Generate unique tracking number: TRX-{date}-{random}
                do {
                    $tracking_number = 'TRX-' . date('YmdHis') . '-' . random_int(10000, 99999);
                } while (self::where('tracking_number', $tracking_number)->exists());
                
                $transaction->tracking_number = $tracking_number;
            }
        });
    }

    /**
     * رابطه با حساب مبدا
     */
    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /**
     * رابطه با حساب مقصد
     */
    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
