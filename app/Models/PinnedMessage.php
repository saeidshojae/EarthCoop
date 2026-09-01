<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PinnedMessage extends Model
{
    use HasFactory;

    protected $fillable = ['message_id', 'group_id', 'pinned_by', 'announcement_id', 'content_type', 'content_id'];

    public const CONTENT_MODELS = [
        'message' => Message::class,
        'post' => Blog::class,
        'poll' => Poll::class,
        'announcement' => Announcement::class,
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function pinnedBy()
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function content()
    {
        return $this->morphTo(__FUNCTION__, 'content_type', 'content_id');
    }

    public function getContentKeyAttribute(): string
    {
        return $this->content_type . ':' . $this->content_id;
    }
}
