<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionReviewAuditAccess extends Model
{
    protected $fillable = ['review_id', 'actor_user_id', 'authority_path', 'purpose', 'scope', 'accessed_at'];
    protected $casts = ['scope' => 'array', 'accessed_at' => 'datetime'];
}
