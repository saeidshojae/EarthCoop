<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivateConversation extends Model
{
    protected $fillable = [
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'private_conversation_user',
            'private_conversation_id',
            'user_id'
        )->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PrivateMessage::class);
    }
}
