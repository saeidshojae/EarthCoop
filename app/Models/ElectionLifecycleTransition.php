<?php

namespace App\Models;

use App\Enums\Elections\ElectionLifecycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectionLifecycleTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'from_status',
        'to_status',
        'reason',
        'source',
        'actor_user_id',
        'reference',
        'metadata',
        'transitioned_at',
    ];

    protected $casts = [
        'from_status' => ElectionLifecycleStatus::class,
        'to_status' => ElectionLifecycleStatus::class,
        'metadata' => 'array',
        'transitioned_at' => 'datetime',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
