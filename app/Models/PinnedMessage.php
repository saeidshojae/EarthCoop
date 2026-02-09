<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PinnedMessage extends Model
{
    use HasFactory;

    protected $fillable = ['message_id', 'group_id', 'pinned_by', 'announcement_id'];

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
} 