<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionPolicyOverride extends Model
{
    protected $fillable = [
        'election_id','from_policy_version_id','to_policy_version_id','actor_user_id',
        'reason','lifecycle_status','metadata','applied_at',
    ];
    protected $casts = ['metadata'=>'array','applied_at'=>'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Election policy override audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('Election policy override audit records cannot be deleted.'));
    }

    public function election() { return $this->belongsTo(Election::class); }
    public function fromPolicy() { return $this->belongsTo(ElectionPolicyVersion::class,'from_policy_version_id'); }
    public function toPolicy() { return $this->belongsTo(ElectionPolicyVersion::class,'to_policy_version_id'); }
    public function actor() { return $this->belongsTo(User::class,'actor_user_id'); }
}
