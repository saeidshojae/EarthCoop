<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\NajmBahar\Models\Account;

class SubAccount extends Model
{
    protected $table = 'najm_sub_accounts';
    protected $fillable = [
        'account_id',
        'sub_account_code', // e.g. 0000000000-001
        'name',
        'balance',
        'meta',
        'status'
    ];

    protected $casts = [
        'balance' => 'integer',
        'meta' => 'array',
    ];

    public $timestamps = true;

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
