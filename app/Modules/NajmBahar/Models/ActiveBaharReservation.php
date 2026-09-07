<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveBaharReservation extends Model
{
    public const RESERVED = 'reserved';
    public const SETTLED = 'settled';
    public const RELEASED = 'released';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';

    protected $table = 'najm_active_bahar_reservations';

    protected $fillable = [
        'payer_account_id', 'payee_account_id', 'amount', 'settled_amount', 'refunded_amount',
        'status', 'reference_type', 'reference_id', 'reservation_key', 'settlement_key',
        'release_key', 'metadata', 'reserved_at', 'settled_at', 'released_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'settled_amount' => 'integer',
        'refunded_amount' => 'integer',
        'metadata' => 'array',
        'reserved_at' => 'datetime',
        'settled_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function payerAccount() { return $this->belongsTo(Account::class, 'payer_account_id'); }
    public function payeeAccount() { return $this->belongsTo(Account::class, 'payee_account_id'); }
}
