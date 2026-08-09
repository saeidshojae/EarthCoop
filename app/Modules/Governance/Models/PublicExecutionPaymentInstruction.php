<?php

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use Illuminate\Database\Eloquent\Model;

class PublicExecutionPaymentInstruction extends Model
{
    protected $table = 'governance_public_execution_payment_instructions';

    protected $fillable = [
        'plan_id', 'authorization_id', 'execution_account_id', 'payee_account_id',
        'created_by', 'approved_by', 'cancelled_by', 'amount_gol', 'status',
        'attempts', 'last_attempt_at', 'failed_at', 'failure_reason',
        'idempotency_key', 'purpose', 'evidence', 'metadata', 'approved_at',
        'cancelled_at', 'cancellation_reason', 'executed_at',
    ];

    protected $casts = [
        'amount_gol' => 'integer',
        'attempts' => 'integer',
        'evidence' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(PublicContributionPlan::class, 'plan_id'); }
    public function authorization() { return $this->belongsTo(PublicExecutionAuthorization::class, 'authorization_id'); }
    public function executionAccount() { return $this->belongsTo(Account::class, 'execution_account_id'); }
    public function payeeAccount() { return $this->belongsTo(Account::class, 'payee_account_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
