<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupFeedItem extends Model
{
    protected $fillable = ['group_id', 'sequence', 'type', 'content_id', 'actor_id', 'version', 'occurred_at'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
