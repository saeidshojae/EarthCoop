<?php

namespace App\Models;

use App\Modules\NajmBahar\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FounderNajmBaharProjectReviewDraft extends Model
{
    protected $fillable = [
        'project_id', 'requested_by_user_id', 'status', 'summary', 'findings', 'reason_code',
    ];

    protected $casts = [
        'findings' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
