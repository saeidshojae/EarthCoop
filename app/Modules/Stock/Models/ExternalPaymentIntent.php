<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalPaymentIntent extends Model
{
    public const CREATED = 'created';
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const REVERSED = 'reversed';

    protected $table = 'stock_external_payment_intents';
    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'quote_snapshot' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function reconciliations()
    {
        return $this->hasMany(ExternalPaymentReconciliation::class, 'payment_intent_id');
    }
}
