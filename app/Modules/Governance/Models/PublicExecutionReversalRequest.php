<?php

namespace App\Modules\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PublicExecutionReversalRequest extends Model
{
    protected $table = 'governance_public_execution_reversal_requests';

    protected $fillable = [
        'payment_instruction_id', 'created_by', 'approved_by', 'cancelled_by',
        'amount_gol', 'status', 'idempotency_key', 'reason', 'evidence', 'metadata',
        'approved_at', 'cancelled_at', 'cancellation_reason', 'executed_at',
    ];

    protected $casts = [
        'amount_gol' => 'integer',
        'evidence' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function paymentInstruction()
    {
        return $this->belongsTo(PublicExecutionPaymentInstruction::class, 'payment_instruction_id');
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
