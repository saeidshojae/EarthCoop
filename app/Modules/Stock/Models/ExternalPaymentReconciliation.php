<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ExternalPaymentReconciliation extends Model
{
    protected $table = 'stock_external_payment_reconciliations';
    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'provider_payload' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('External payment reconciliation history is append-only.'));
        static::deleting(fn () => throw new RuntimeException('External payment reconciliation history cannot be deleted.'));
    }

    public function paymentIntent()
    {
        return $this->belongsTo(ExternalPaymentIntent::class, 'payment_intent_id');
    }
}
