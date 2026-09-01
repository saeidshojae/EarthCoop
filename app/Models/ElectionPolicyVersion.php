<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionPolicyVersion extends Model
{
    protected $fillable = [
        'group_setting_id', 'level_key', 'version', 'election_status',
        'manager_count', 'inspector_count', 'voting_duration_days',
        'start_threshold', 'cycle_interval_months', 'response_duration_days',
        'report_min_distinct_voters', 'report_bucket_days', 'meaningful_trend_min_net_change',
        'manager_contract_version_id', 'inspector_contract_version_id',
        'effective_at', 'retired_at', 'created_by', 'change_reason', 'metadata',
    ];

    protected $casts = [
        'election_status' => 'boolean',
        'manager_count' => 'integer',
        'inspector_count' => 'integer',
        'voting_duration_days' => 'integer',
        'start_threshold' => 'integer',
        'cycle_interval_months' => 'integer',
        'response_duration_days' => 'integer',
        'report_min_distinct_voters' => 'integer',
        'report_bucket_days' => 'integer',
        'meaningful_trend_min_net_change' => 'integer',
        'effective_at' => 'datetime',
        'retired_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (ElectionPolicyVersion $policy): void {
            if ($policy->getOriginal('effective_at') !== null) {
                $allowed = array_keys($policy->getDirty());
                sort($allowed);
                $retirementOnly = $allowed === ['retired_at', 'updated_at'] || $allowed === ['retired_at'];
                if (! $retirementOnly) {
                    throw new LogicException('Published election policy versions are immutable; publish a new version instead.');
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Election policy history is immutable.');
        });
    }

    public function groupSetting() { return $this->belongsTo(GroupSetting::class); }
    public function elections() { return $this->hasMany(Election::class, 'policy_version_id'); }
    public function managerContractVersion() { return $this->belongsTo(ElectionResponsibilityContractVersion::class, 'manager_contract_version_id'); }
    public function inspectorContractVersion() { return $this->belongsTo(ElectionResponsibilityContractVersion::class, 'inspector_contract_version_id'); }
}
