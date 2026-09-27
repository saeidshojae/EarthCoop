<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'user_id',
        'conversation_id',
        'action',
        'contract_version',
        'risk',
        'mode',
        'input',
        'input_hash',
        'expected_output',
        'status',
        'consent_required',
        'consented_at',
        'consent_evidence_id',
        'apply_idempotency_key',
        'applied_at',
        'evidence_id',
        'runtime_run_id',
        'result',
        'error_code',
    ];

    protected $casts = [
        'input' => 'array',
        'expected_output' => 'array',
        'result' => 'array',
        'consent_required' => 'boolean',
        'consented_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
