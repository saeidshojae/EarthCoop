<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryFund extends Model
{
    protected $table = 'najm_bahar_treasury_funds';

    protected $fillable = [
        'code',
        'name',
        'account_id',
        'purpose',
        'required_reserve',
        'committed_liabilities',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'required_reserve' => 'integer',
        'committed_liabilities' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function availableSurplus(): int
    {
        $balance = (int) ($this->account?->balance_active ?? 0);
        $protected = (int) $this->required_reserve + (int) $this->committed_liabilities;

        return max(0, $balance - $protected);
    }
}
