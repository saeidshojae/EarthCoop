<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReferenceSettlement extends Model
{
    protected $fillable = [
        'source', 'dataset_version', 'external_id', 'parent_external_id',
        'source_code', 'source_row_id', 'name_fa', 'search_name',
        'classification', 'residential_eligibility', 'governance_authorized',
        'operational_promotion_allowed', 'provenance',
    ];

    public function residenceClaims(): HasMany
    {
        return $this->hasMany(ReferenceSettlementResidenceClaim::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReferenceSettlementReview::class);
    }

    protected $casts = [
        'governance_authorized' => 'boolean',
        'operational_promotion_allowed' => 'boolean',
        'provenance' => 'array',
    ];
}
