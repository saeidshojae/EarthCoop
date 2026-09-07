<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointTransaction extends Model
{
    protected $table = 'user_point_transactions';

    protected $fillable = [
        'user_id', 'delta', 'balance_after', 'action', 'dimension', 'convertible',
        'source', 'reference_id', 'event_key', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'convertible' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function consumptions()
    {
        return $this->hasMany(UserPointConsumption::class, 'user_point_transaction_id');
    }
}
