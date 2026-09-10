<?php

namespace App\Models;

use App\Enums\Membership\GroupCreationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupCreationPolicy extends Model
{
    protected $fillable = [
        'membership_dimension_id',
        'governance_area_id',
        'governance_type',
        'governance_rank',
        'mode',
        'threshold',
        'priority',
        'enabled',
        'metadata',
    ];

    protected $casts = [
        'mode' => GroupCreationMode::class,
        'governance_rank' => 'integer',
        'threshold' => 'integer',
        'priority' => 'integer',
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(MembershipDimension::class, 'membership_dimension_id');
    }

    public function governanceArea(): BelongsTo
    {
        return $this->belongsTo(GovernanceArea::class);
    }
}
