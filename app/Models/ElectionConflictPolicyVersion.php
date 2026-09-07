<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionConflictPolicyVersion extends Model
{
    protected $fillable = ['version', 'effective_at', 'retired_at', 'created_by', 'change_reason'];
    protected $casts = ['effective_at' => 'datetime', 'retired_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published election conflict policy versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Published election conflict policy versions cannot be deleted.'));
    }

    public function rules() { return $this->hasMany(ElectionConflictPolicyRule::class, 'policy_version_id'); }
}
