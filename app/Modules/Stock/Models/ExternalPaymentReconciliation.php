<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function paymentIntent()
    {
        return $this->belongsTo(ExternalPaymentIntent::class, 'payment_intent_id');
    }
}
