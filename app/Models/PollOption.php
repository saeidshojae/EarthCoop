<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    use HasFactory;
    protected $fillable = ['poll_id', 'text'];
    public $timestamps = false;
    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class, 'option_id');
    }

    // تعداد آرا برای این گزینه
    public function votesCount()
    {
        return $this->votes()->count();
    }

}
