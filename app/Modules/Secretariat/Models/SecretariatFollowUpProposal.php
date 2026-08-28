<?php

namespace App\Modules\Secretariat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretariatFollowUpProposal extends Model
{
    protected $guarded = [];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(SecretariatDispatch::class, 'dispatch_id');
    }
}
