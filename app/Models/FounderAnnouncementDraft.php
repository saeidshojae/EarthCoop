<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderAnnouncementDraft extends Model
{
    protected $guarded=[];

    protected $casts=[
        'should_pin'=>'boolean',
        'published_at'=>'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
