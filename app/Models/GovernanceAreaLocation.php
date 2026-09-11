<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GovernanceAreaLocation extends Pivot
{
    protected $table = 'governance_area_locations';

    public $incrementing = false;

    protected $fillable = [
        'governance_area_id',
        'location_id',
    ];

    public function governanceArea(): BelongsTo
    {
        return $this->belongsTo(GovernanceArea::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
