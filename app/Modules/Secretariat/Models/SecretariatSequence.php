<?php

namespace App\Modules\Secretariat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretariatSequence extends Model
{
    protected $guarded = [];

    public function office(): BelongsTo
    {
        return $this->belongsTo(SecretariatOffice::class, 'office_id');
    }
}
