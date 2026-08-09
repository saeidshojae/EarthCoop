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
        'created_by', 'amount_gol', 'status', 'idempotency_key', 'purpose',
        'evidence', 'metadata', 'approved_at', 'executed_at',
    ];

    protected $casts = [
        'amount_gol' => 'integer',
        'evidence' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(PublicContributionPlan::class, 'plan_id'); }
    public function authorization() { return $this->belongsTo(PublicExecutionAuthorization::class, 'authorization_id'); }
    public function executionAccount() { return $this->belongsTo(Account::class, 'execution_account_id'); }
    public function payeeAccount() { return $this->belongsTo(Account::class, 'payee_account_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
