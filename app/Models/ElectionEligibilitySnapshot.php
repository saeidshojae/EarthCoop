<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectionEligibilitySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'user_id',
        'voter_eligible',
        'selectable_eligible',
        'voter_exclusion_reason',
        'selectable_exclusion_reason',
        'membership_role',
        'membership_status',
        'snapshot_version',
        'captured_at',
    ];

    protected $casts = [
        'voter_eligible' => 'boolean',
        'selectable_eligible' => 'boolean',
        'captured_at' => 'datetime',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }
}
