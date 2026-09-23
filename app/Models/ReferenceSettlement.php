<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ReferenceSettlement extends Model
{
    protected $fillable = [
        'source', 'dataset_version', 'external_id', 'parent_external_id',
        'source_code', 'source_row_id', 'name_fa', 'search_name',
        'classification', 'residential_eligibility', 'governance_authorized',
        'operational_promotion_allowed', 'provenance',
    ];

    protected $casts = [
        'governance_authorized' => 'boolean',
        'operational_promotion_allowed' => 'boolean',
        'provenance' => 'array',
    ];
}
