<?php

namespace App\Modules\Secretariat\Models;

use App\Modules\Secretariat\Services\SecretariatMorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SecretariatOffice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'numbering_policy' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        SecretariatMorphMap::register();
    }

    public function scope(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'scope_type', 'scope_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(SecretariatRecord::class, 'office_id');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(SecretariatSequence::class, 'office_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(SecretariatAuditEvent::class, 'office_id');
    }
}
