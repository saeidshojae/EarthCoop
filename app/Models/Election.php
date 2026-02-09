<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'starts_at', 'ends_at', 'is_closed','second_finish_time'];

    public function group(){
        return $this->belongsTo(Group::class);
    }

    public function candidates(){
        return $this->hasMany(Candidate::class);
    }
    public function yourVotes()
    {
        return $this->hasMany(Vote::class, 'election_id') // کلید خارجی پیش‌فرض
                    ->where('voter_id', auth()->id());   // فیلتر با فیلد درست
    }
    
}
