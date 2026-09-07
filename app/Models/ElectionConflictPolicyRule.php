<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionConflictPolicyRule extends Model
{
    public const DECISIONS = ['allowed', 'forbidden', 'allowed_with_suspension'];
    public const DOMAINS = ['public', 'job', 'experience', 'age', 'gender'];
    public const POSITIONS = ['manager', 'inspector'];

    protected $fillable = [
        'policy_version_id', 'current_position', 'current_domain_type', 'current_level',
        'new_position', 'new_domain_type', 'new_level', 'decision', 'reason',
    ];

    public function policyVersion() { return $this->belongsTo(ElectionConflictPolicyVersion::class, 'policy_version_id'); }
}
