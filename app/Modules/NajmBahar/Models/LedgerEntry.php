<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'najm_ledger_entries';
    protected $fillable = [
        'transaction_id',
        'account_id',
        'amount',
        'entry_type', // debit|credit
        'meta'
    ];

    protected $casts = [
        'amount' => 'integer',
        'meta' => 'array',
    ];

    public $timestamps = true;
}
