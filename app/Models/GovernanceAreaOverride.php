<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceAreaOverride extends Model
{
    protected $fillable = [
        'governance_area_id',
        'capabilities',
    ];

    protected $casts = [
        'capabilities' => 'array',
    ];

    public function governanceArea(): BelongsTo
    {
        return $this->belongsTo(GovernanceArea::class);
    }
}
