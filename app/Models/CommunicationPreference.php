<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationPreference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'topic_key', 'channel', 'preference', 'frequency'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
