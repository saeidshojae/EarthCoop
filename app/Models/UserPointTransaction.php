<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointTransaction extends Model
{
    protected $table = 'user_point_transactions';

    protected $fillable = [
        'user_id',
        'delta',
        'balance_after',
        'action',
        'source',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
